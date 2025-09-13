<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_Settings {

  const OPT_KEY = 'mcs_settings';

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'add_settings_page']);
    add_action('admin_init', [__CLASS__, 'register']);
    add_action('admin_post_mcs_manual_sync', [__CLASS__, 'handle_manual_sync']);
  }

  public static function defaults() {
    return [
      'cpt' => 'property',
      'ics_urls' => [],
      'interval' => 'hourly'
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

  public static function render() {
    if ( ! current_user_can('manage_options') ) return;
    $o = self::get();
    ?>
    <div class="wrap">
      <h1><?php _e('Minpaku Sync Settings', 'minpaku-channel-sync'); ?></h1>

      <?php if ( isset($_GET['mcs_synced']) ): ?>
        <div class="notice notice-success"><p><?php _e('Manual sync executed.', 'minpaku-channel-sync'); ?></p></div>
      <?php endif; ?>

      <form method="post" action="options.php">
        <?php settings_fields('mcs_settings_group'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="mcs_cpt"><?php _e('Target Post Type (slug)', 'minpaku-channel-sync'); ?></label></th>
            <td><input name="<?php echo self::OPT_KEY; ?>[cpt]" id="mcs_cpt" type="text" value="<?php echo esc_attr($o['cpt']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="mcs_ics_urls"><?php _e('ICS import URLs (one per line)', 'minpaku-channel-sync'); ?></label></th>
            <td><textarea name="<?php echo self::OPT_KEY; ?>[ics_urls]" id="mcs_ics_urls" rows="6" class="large-text"><?php echo esc_textarea(implode("\n",$o['ics_urls'])); ?></textarea></td>
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
      <h2><?php _e('Recent Logs', 'minpaku-channel-sync'); ?></h2>
      <table class="widefat striped">
        <thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach (MCS_Logger::get_logs(20) as $row): ?>
          <tr>
            <td><?php echo esc_html($row['time']); ?></td>
            <td><?php echo esc_html($row['level']); ?></td>
            <td><?php echo esc_html($row['message']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
  }
}
