<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_Settings {

  const OPT_KEY = 'mcs_settings';

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'add_settings_page']);
    add_action('admin_init', [__CLASS__, 'register']);
    add_action('admin_post_mcs_manual_sync', [__CLASS__, 'handle_manual_sync']);
    add_action('admin_post_mcs_clear_logs', [__CLASS__, 'handle_clear_logs']);
  }

  public static function defaults() {
    return [
      'cpt' => 'property',
      'ics_urls' => [],
      'mappings' => [],
      'interval' => 'hourly',
      'export_disposition' => 'inline',
      'flush_rewrite_rules' => false
    ];
  }

  public static function get() {
    $opts = get_option(self::OPT_KEY, []);
    return wp_parse_args($opts, self::defaults());
  }

  public static function add_settings_page() {
    add_options_page(
      __('Minpaku Sync', 'minpaku-channel-sync'),
      __('Minpaku Sync', 'minpaku-channel-sync'),
      'manage_options',
      'mcs-settings',
      [__CLASS__, 'render']
    );
  }

  public static function register() {
    register_setting('mcs_settings_group', self::OPT_KEY, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize']
    ]);
  }

  public static function sanitize($input) {
    $out = self::get();
    if (isset($input['cpt'])) {
      $out['cpt'] = sanitize_key($input['cpt']);
    }
    if (isset($input['interval'])) {
      $allowed = ['hourly','2hours','6hours'];
      $out['interval'] = in_array($input['interval'], $allowed, true) ? $input['interval'] : 'hourly';
    }
    if (isset($input['ics_urls'])) {
      // textarea with one URL per line
      $lines = array_filter(array_map('trim', explode("\n", $input['ics_urls'])));
      $urls = [];
      foreach ($lines as $u) {
        $u = esc_url_raw($u);
        if ($u) $urls[] = $u;
      }
      $out['ics_urls'] = $urls;
    }
    if (isset($input['mappings'])) {
      $mappings = [];
      if (is_array($input['mappings'])) {
        foreach ($input['mappings'] as $mapping) {
          $url = isset($mapping['url']) ? esc_url_raw(trim($mapping['url'])) : '';
          $post_id = isset($mapping['post_id']) ? absint($mapping['post_id']) : 0;
          if ($url && $post_id) {
            $mappings[] = ['url' => $url, 'post_id' => $post_id];
          }
        }
      }
      $out['mappings'] = $mappings;
      
      // Migration from old ics_urls format (one-time)
      if (empty($mappings) && !empty($out['ics_urls'])) {
        MCS_Logger::log('INFO', 'Migrating from old ics_urls format to mappings (post_id required)');
      }
    }
    if (isset($input['export_disposition'])) {
      $allowed_dispositions = ['inline', 'attachment'];
      $out['export_disposition'] = in_array($input['export_disposition'], $allowed_dispositions, true) ? $input['export_disposition'] : 'inline';
    }
    if (isset($input['flush_rewrite_rules'])) {
      $flush_requested = (bool) $input['flush_rewrite_rules'];
      if ($flush_requested) {
        flush_rewrite_rules(false);
        MCS_Logger::log('INFO', 'Rewrite rules flushed via settings.');
      }
      // Always reset to false after processing
      $out['flush_rewrite_rules'] = false;
    }

    // Re-schedule cron if interval changed
    if ($out['interval'] !== self::get()['interval']) {
      MCS_Cron::reschedule($out['interval']);
    }

    return $out;
  }

  public static function handle_manual_sync() {
    if ( ! current_user_can('manage_options') ) {
      wp_die('forbidden');
    }
    check_admin_referer('mcs_manual_sync');
    MCS_Logger::log('INFO', 'Manual sync triggered by admin.');
    MCS_ICS_Importer::run();
    wp_redirect( add_query_arg('mcs_synced', '1', admin_url('options-general.php?page=mcs-settings')) );
    exit;
  }

  public static function handle_clear_logs() {
    if ( ! current_user_can('manage_options') ) {
      wp_die('forbidden');
    }
    check_admin_referer('mcs_clear_logs');
    MCS_Logger::clear();
    wp_redirect( add_query_arg('logs_cleared', '1', admin_url('options-general.php?page=mcs-settings')) );
    exit;
  }

  public static function render() {
    if ( ! current_user_can('manage_options') ) return;
    $o = self::get();
    ?>
    <div class="wrap">
      <h1><?php _e('Minpaku Sync Settings', 'minpaku-channel-sync'); ?></h1>

      <?php if ( isset($_GET['mcs_synced']) ): ?>
        <div class="notice notice-success"><p><?php _e('Manual sync executed.', 'minpaku-channel-sync'); ?></p></div>
      <?php endif; ?>
      <?php if ( isset($_GET['logs_cleared']) ): ?>
        <div class="notice notice-success"><p><?php _e('Logs cleared successfully.', 'minpaku-channel-sync'); ?></p></div>
        <?php 
        $sync_results = get_transient('mcs_last_sync_results');
        if ($sync_results): ?>
          <div class="notice notice-info">
            <h4><?php _e('Sync Results', 'minpaku-channel-sync'); ?></h4>
            <p><strong><?php _e('Total:', 'minpaku-channel-sync'); ?></strong> 
              <?php printf(__('Added: %d, Updated: %d, Skipped: %d, Errors: %d', 'minpaku-channel-sync'), 
                $sync_results['total']['added'], $sync_results['total']['updated'], 
                $sync_results['total']['skipped'], $sync_results['total']['errors']); ?>
            </p>
            <?php if (!empty($sync_results['by_url'])): ?>
              <details>
                <summary><?php _e('Details by URL', 'minpaku-channel-sync'); ?></summary>
                <ul>
                  <?php foreach ($sync_results['by_url'] as $url => $stats): ?>
                    <li><strong><?php echo esc_html($url); ?>:</strong> 
                      <?php printf(__('Added: %d, Updated: %d, Skipped: %d', 'minpaku-channel-sync'), 
                        $stats['added'], $stats['updated'], $stats['skipped']); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php 
      $next_cron = wp_next_scheduled('mcs_sync_event');
      if ($next_cron): ?>
        <div class="notice notice-info">
          <p><strong><?php _e('Next scheduled sync:', 'minpaku-channel-sync'); ?></strong> 
            <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_cron)); ?>
          </p>
        </div>
      <?php endif; ?>

      <form method="post" action="options.php">
        <?php settings_fields('mcs_settings_group'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="mcs_cpt"><?php _e('Target Post Type (slug)', 'minpaku-channel-sync'); ?></label></th>
            <td><input name="<?php echo self::OPT_KEY; ?>[cpt]" id="mcs_cpt" type="text" value="<?php echo esc_attr($o['cpt']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><?php _e('ICS Import Mappings', 'minpaku-channel-sync'); ?></th>
            <td>
              <table class="widefat" id="mcs-mappings-table">
                <thead>
                  <tr>
                    <th><?php _e('ICS URL', 'minpaku-channel-sync'); ?></th>
                    <th><?php _e('Post ID', 'minpaku-channel-sync'); ?></th>
                    <th><?php _e('Action', 'minpaku-channel-sync'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($o['mappings'] as $i => $mapping): ?>
                  <tr>
                    <td><input type="url" name="<?php echo self::OPT_KEY; ?>[mappings][<?php echo $i; ?>][url]" value="<?php echo esc_attr($mapping['url']); ?>" class="regular-text" /></td>
                    <td><input type="number" name="<?php echo self::OPT_KEY; ?>[mappings][<?php echo $i; ?>][post_id]" value="<?php echo esc_attr($mapping['post_id']); ?>" min="1" class="small-text" /></td>
                    <td><button type="button" class="button mcs-remove-mapping"><?php _e('Remove', 'minpaku-channel-sync'); ?></button></td>
                  </tr>
                  <?php endforeach; ?>
                  <tr id="mcs-new-mapping-row">
                    <td><input type="url" name="<?php echo self::OPT_KEY; ?>[mappings][new][url]" placeholder="https://example.com/calendar.ics" class="regular-text" /></td>
                    <td><input type="number" name="<?php echo self::OPT_KEY; ?>[mappings][new][post_id]" placeholder="123" min="1" class="small-text" /></td>
                    <td><button type="button" class="button" id="mcs-add-mapping"><?php _e('Add', 'minpaku-channel-sync'); ?></button></td>
                  </tr>
                </tbody>
              </table>
              <script>
              jQuery(document).ready(function($) {
                var mappingIndex = <?php echo count($o['mappings']); ?>;
                $('#mcs-add-mapping').on('click', function() {
                  var url = $('input[name="<?php echo self::OPT_KEY; ?>[mappings][new][url]"]').val();
                  var postId = $('input[name="<?php echo self::OPT_KEY; ?>[mappings][new][post_id]"]').val();
                  if (url && postId) {
                    var newRow = '<tr>' +
                      '<td><input type="url" name="<?php echo self::OPT_KEY; ?>[mappings][' + mappingIndex + '][url]" value="' + url + '" class="regular-text" /></td>' +
                      '<td><input type="number" name="<?php echo self::OPT_KEY; ?>[mappings][' + mappingIndex + '][post_id]" value="' + postId + '" min="1" class="small-text" /></td>' +
                      '<td><button type="button" class="button mcs-remove-mapping"><?php _e('Remove', 'minpaku-channel-sync'); ?></button></td>' +
                      '</tr>';
                    $('#mcs-new-mapping-row').before(newRow);
                    $('input[name="<?php echo self::OPT_KEY; ?>[mappings][new][url]"]').val('');
                    $('input[name="<?php echo self::OPT_KEY; ?>[mappings][new][post_id]"]').val('');
                    mappingIndex++;
                  }
                });
                $(document).on('click', '.mcs-remove-mapping', function() {
                  $(this).closest('tr').remove();
                });
              });
              </script>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php _e('Sync interval', 'minpaku-channel-sync'); ?></th>
            <td>
              <select name="<?php echo self::OPT_KEY; ?>[interval]">
                <option value="hourly" <?php selected($o['interval'],'hourly'); ?>>hourly</option>
                <option value="2hours" <?php selected($o['interval'],'2hours'); ?>>2 hours</option>
                <option value="6hours" <?php selected($o['interval'],'6hours'); ?>>6 hours</option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php _e('ICS Export Content-Disposition', 'minpaku-channel-sync'); ?></th>
            <td>
              <select name="<?php echo self::OPT_KEY; ?>[export_disposition]">
                <option value="inline" <?php selected($o['export_disposition'],'inline'); ?>><?php _e('Inline (browser display)', 'minpaku-channel-sync'); ?></option>
                <option value="attachment" <?php selected($o['export_disposition'],'attachment'); ?>><?php _e('Attachment (download)', 'minpaku-channel-sync'); ?></option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php _e('Flush Rewrite Rules', 'minpaku-channel-sync'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo self::OPT_KEY; ?>[flush_rewrite_rules]" value="1" />
                <?php _e('Flush rewrite rules on save (use only when needed)', 'minpaku-channel-sync'); ?>
              </label>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <hr/>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mcs_manual_sync'); ?>
        <input type="hidden" name="action" value="mcs_manual_sync"/>
        <?php submit_button(__('Run manual sync now', 'minpaku-channel-sync'), 'secondary'); ?>
      </form>

      <hr/>
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2><?php _e('Recent Logs (Latest 50)', 'minpaku-channel-sync'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0;">
          <?php wp_nonce_field('mcs_clear_logs'); ?>
          <input type="hidden" name="action" value="mcs_clear_logs"/>
          <input type="submit" class="button button-secondary" value="<?php _e('Clear Logs', 'minpaku-channel-sync'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'minpaku-channel-sync'); ?>');" />
        </form>
      </div>
      <table class="widefat striped">
        <thead><tr><th><?php _e('Time', 'minpaku-channel-sync'); ?></th><th><?php _e('Level', 'minpaku-channel-sync'); ?></th><th><?php _e('Message', 'minpaku-channel-sync'); ?></th><th><?php _e('Context', 'minpaku-channel-sync'); ?></th></tr></thead>
        <tbody>
        <?php foreach (MCS_Logger::get_logs(50) as $row): ?>
          <tr>
            <td><?php echo esc_html($row['time']); ?></td>
            <td><span class="mcs-log-level mcs-log-<?php echo esc_attr(strtolower($row['level'])); ?>"><?php echo esc_html($row['level']); ?></span></td>
            <td><?php echo esc_html($row['message']); ?></td>
            <td>
              <?php 
              if (!empty($row['context']) && is_array($row['context'])) {
                $context_parts = [];
                foreach ($row['context'] as $key => $value) {
                  if (is_scalar($value)) {
                    $context_parts[] = esc_html($key) . ': ' . esc_html($value);
                  }
                }
                echo implode(', ', $context_parts);
              }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <style>
      .mcs-log-level {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
      }
      .mcs-log-info { background: #d1ecf1; color: #0c5460; }
      .mcs-log-warning { background: #fff3cd; color: #856404; }
      .mcs-log-error { background: #f8d7da; color: #721c24; }
      </style>
    </div>
    <?php
  }
}
