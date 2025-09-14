<?php
if ( ! defined('ABSPATH') ) exit;

class MCS_Settings {

  const OPT_KEY = 'mcs_settings';

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'add_settings_page']);
    add_action('admin_init', [__CLASS__, 'register']);
    add_action('admin_post_mcs_manual_sync', [__CLASS__, 'handle_manual_sync']);
    add_action('admin_post_mcs_clear_logs', [__CLASS__, 'handle_clear_logs']);
    add_action('admin_notices', [__CLASS__, 'admin_notices']);
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

  public static function admin_notices() {
    if (!current_user_can('manage_options')) {
      return;
    }

    // Settings saved successfully
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'minpaku-channel-sync') . '</p></div>';
    }

    // Manual sync completed
    if (isset($_GET['mcs_synced'])) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Manual sync executed.', 'minpaku-channel-sync') . '</p></div>';
    }

    // Logs cleared
    if (isset($_GET['logs_cleared'])) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logs cleared successfully.', 'minpaku-channel-sync') . '</p></div>';
    }

  }

public static function sanitize( $input ) {
	// Security: Verify user capabilities and nonce
	if ( ! current_user_can( 'manage_options' ) ) {
		add_settings_error( self::OPT_KEY, 'access_denied', __( 'You do not have sufficient permissions to access this page.', 'minpaku-channel-sync' ) );
		return get_option( self::OPT_KEY, self::defaults() );
	}

	// Handle HTTP cache clear button
	if ( isset( $_POST['mcs_clear_http_cache'] ) ) {
		if ( wp_verify_nonce( $_POST['mcs_clear_http_cache_nonce'] ?? '', 'mcs_clear_http_cache' ) ) {
			delete_option( 'mcs_http_cache' );
			add_settings_error( self::OPT_KEY, 'mcs_cache_cleared', __( 'HTTP cache cleared.', 'minpaku-channel-sync' ), 'updated' );
			return get_option( self::OPT_KEY, self::defaults() );
		} else {
			add_settings_error( self::OPT_KEY, 'cache_clear_nonce_failed', __( 'Security check failed for cache clear. Please try again.', 'minpaku-channel-sync' ) );
			return get_option( self::OPT_KEY, self::defaults() );
		}
	}

	// Additional nonce verification for settings page
	if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'mcs_settings_group-options' ) ) {
		add_settings_error( self::OPT_KEY, 'nonce_failed', __( 'Security check failed. Please try again.', 'minpaku-channel-sync' ) );
		return get_option( self::OPT_KEY, self::defaults() );
	}

	// ① 入力の型を保証
	if ( ! is_array( $input ) ) {
		$input = array();
	}

	$out = array();

	// ② 単純項目
	$out['cpt'] = isset( $input['cpt'] ) ? sanitize_text_field( $input['cpt'] ) : 'property';
	$out['interval'] = isset( $input['interval'] ) && in_array( $input['interval'], ['hourly', '2hours', '6hours'], true ) ? $input['interval'] : 'hourly';
	$disp = isset( $input['export_disposition'] ) ? $input['export_disposition'] : '';
	$out['export_disposition'] = in_array( $disp, array( 'inline', 'attachment' ), true ) ? $disp : 'inline';
	$out['flush_rewrite_rules'] = ! empty( $input['flush_rewrite_rules'] ) ? 1 : 0;

	// ③ マッピング正規化（配列/JSON/テキスト全部OK）
	$mappings = array();
	$invalid  = 0;

	$push = function ( $url, $pid ) use ( &$mappings, &$invalid ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		$pid = absint( $pid );
		if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) && $pid > 0 ) {
			$mappings[] = array( 'url' => esc_url_raw( $url ), 'post_id' => $pid );
		} else {
			$invalid++;
		}
	};

	// 3-1) 新形式: mappings
	if ( array_key_exists( 'mappings', $input ) ) {
		$m = $input['mappings'];

		if ( is_array( $m ) ) {
			// 行配列: [ {url:'', post_id:''}, ... ]
			if ( isset( $m[0] ) && is_array( $m[0] ) ) {
				foreach ( $m as $row ) {
					$push( $row['url'] ?? '', $row['post_id'] ?? 0 );
				}
			} else {
				// カラム配列: { url:[], post_id:[] }
				$urls = isset( $m['url'] ) ? (array) $m['url'] : array();
				$pids = isset( $m['post_id'] ) ? (array) $m['post_id'] : array();
				$n    = max( count( $urls ), count( $pids ) );
				for ( $i = 0; $i < $n; $i++ ) {
					$push( $urls[ $i ] ?? '', $pids[ $i ] ?? 0 );
				}
			}
		} elseif ( is_string( $m ) ) {
			// JSON か 1行1件のテキスト "URL,post_id"
			$decoded = json_decode( $m, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $row ) {
					$push( $row['url'] ?? '', $row['post_id'] ?? 0 );
				}
			} else {
				$lines = preg_split( '/\R/', $m ) ?: array();
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					$parts = array_map( 'trim', explode( ',', $line ) );
					$push( $parts[0] ?? '', $parts[1] ?? 0 );
				}
			}
		}
	}

	// 3-2) 旧形式: ics_urls（後方互換。post_id不明→無効としてカウントのみ）
	if ( array_key_exists( 'ics_urls', $input ) ) {
		$old = $input['ics_urls'];
		if ( is_array( $old ) ) {
			foreach ( $old as $url ) { $push( $url, 0 ); }
		} elseif ( is_string( $old ) ) {
			$lines = preg_split( '/\R/', $old ) ?: array();
			foreach ( $lines as $url ) { $push( $url, 0 ); }
		}
	}

	$out['mappings'] = $mappings;

	// ④ flushオプション
	if ( ! empty( $out['flush_rewrite_rules'] ) && function_exists( 'flush_rewrite_rules' ) ) {
		flush_rewrite_rules( false );
	}

	// ⑤ 無効行の処理とnotice表示
	if ( $invalid > 0 ) {
		// Log the warning
		if ( class_exists( 'MCS_Logger' ) ) {
			MCS_Logger::warning(
				'Dropped invalid mapping rows during settings sanitize',
				array( 'count' => $invalid )
			);
		}

		// Add settings error for admin notice
		/* translators: %1$d is the number of invalid mapping rows */
		add_settings_error(
			self::OPT_KEY,
			'invalid_mappings',
			sprintf(
				_n(
					'%1$d invalid mapping row was dropped (invalid URL or missing post ID).',
					'%1$d invalid mapping rows were dropped (invalid URL or missing post ID).',
					$invalid,
					'minpaku-channel-sync'
				),
				$invalid
			),
			'warning'
		);
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

      <?php
      $sync_results = get_transient('mcs_last_sync_results');
      if ($sync_results): ?>
        <div class="notice notice-info">
          <h4><?php esc_html_e('Sync Results', 'minpaku-channel-sync'); ?></h4>
          <p><strong><?php esc_html_e('Total:', 'minpaku-channel-sync'); ?></strong>
            <?php
            /* translators: %1$d: added count, %2$d: updated count, %3$d: skipped count, %4$d: not modified count, %5$d: error count */
            printf(esc_html__('Added: %1$d, Updated: %2$d, Skipped: %3$d, Not Modified: %4$d, Errors: %5$d', 'minpaku-channel-sync'),
              $sync_results['total']['added'], $sync_results['total']['updated'],
              $sync_results['total']['skipped'],
              $sync_results['total']['skipped_not_modified'] ?? 0,
              $sync_results['total']['errors']); ?>
          </p>
          <?php if (!empty($sync_results['by_url'])): ?>
            <details>
              <summary><?php esc_html_e('Details by URL', 'minpaku-channel-sync'); ?></summary>
              <ul>
                <?php foreach ($sync_results['by_url'] as $url => $stats): ?>
                  <li><strong><?php echo esc_html($url); ?>:</strong>
                    <?php
                    /* translators: %1$d: added count, %2$d: updated count, %3$d: skipped count, %4$d: not modified count */
                    printf(esc_html__('Added: %1$d, Updated: %2$d, Skipped: %3$d, Not Modified: %4$d', 'minpaku-channel-sync'),
                      $stats['added'], $stats['updated'], $stats['skipped'],
                      $stats['skipped_not_modified'] ?? 0); ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>
        </div>
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
                <option value="hourly" <?php selected($o['interval'],'hourly'); ?>><?php esc_html_e('Hourly', 'minpaku-channel-sync'); ?></option>
                <option value="2hours" <?php selected($o['interval'],'2hours'); ?>><?php esc_html_e('Every 2 hours', 'minpaku-channel-sync'); ?></option>
                <option value="6hours" <?php selected($o['interval'],'6hours'); ?>><?php esc_html_e('Every 6 hours', 'minpaku-channel-sync'); ?></option>
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

        <button type="submit" name="mcs_clear_http_cache" class="button"><?php _e('Clear HTTP cache', 'minpaku-channel-sync'); ?></button>
        <?php wp_nonce_field('mcs_clear_http_cache', 'mcs_clear_http_cache_nonce'); ?>
      </form>

      <hr/>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mcs_manual_sync'); ?>
        <input type="hidden" name="action" value="mcs_manual_sync"/>
        <?php submit_button(__('Run manual sync now', 'minpaku-channel-sync'), 'secondary'); ?>
      </form>


      <hr/>

      <?php
      $warning_error_logs = MCS_Logger::get_warning_error_summary(5);
      if (!empty($warning_error_logs)): ?>
        <div class="notice notice-warning">
          <h4><?php esc_html_e('Recent Warnings & Errors', 'minpaku-channel-sync'); ?></h4>
          <ul style="margin: 10px 0;">
            <?php foreach ($warning_error_logs as $log): ?>
              <li>
                <strong>[<?php echo esc_html($log['level']); ?>]</strong>
                <span><?php echo esc_html($log['time']); ?>:</span>
                <?php echo esc_html($log['message']); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <hr/>
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2><?php esc_html_e('Recent Logs (Latest 50)', 'minpaku-channel-sync'); ?></h2>
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
