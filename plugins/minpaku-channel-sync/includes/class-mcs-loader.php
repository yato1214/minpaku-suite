<?php
if ( ! defined('ABSPATH') ) exit;

final class MCS_Loader {

  public static function init() {
    add_action('plugins_loaded', [__CLASS__, 'load_textdomain']);

    // Query var for ICS endpoint
    add_filter('query_vars', function($vars){
      $vars[] = 'mcs_ics_post_id';
      return $vars;
    });

    // Rewrite rule (activation also sets)
    add_action('init', function() {
      add_rewrite_rule('^ics/([0-9]+)\.ics/?$', 'index.php?mcs_ics_post_id=$matches[1]', 'top');
    });

    // Disable canonical redirect for ICS endpoints only
    add_filter('redirect_canonical', function($redirect_url, $requested_url) {
      // Only disable canonical redirect when actually accessing an ICS endpoint
      if (get_query_var('mcs_ics_post_id')) {
        return false;
      }
      return $redirect_url;
    }, 10, 2);

    // Handle ICS output
    add_action('template_redirect', [__CLASS__, 'maybe_output_ics']);

    // Settings / Cron / Logger / Importer / Exporter
    require_once MCS_PLUGIN_DIR . 'includes/class-mcs-logger.php';
    require_once MCS_PLUGIN_DIR . 'includes/class-mcs-settings.php';
    require_once MCS_PLUGIN_DIR . 'includes/class-mcs-cron.php';
    require_once MCS_PLUGIN_DIR . 'includes/class-mcs-ics-exporter.php';
    require_once MCS_PLUGIN_DIR . 'includes/class-mcs-ics-importer.php';
    require_once MCS_PLUGIN_DIR . 'includes/class-mcs-alerts.php';
    require_once MCS_PLUGIN_DIR . 'includes/cli/class-mcs-cli.php';

    // Load CLI classes only when WP-CLI is available
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
      require_once MCS_PLUGIN_DIR . 'includes/class-mcs-cli-mappings.php';
      require_once MCS_PLUGIN_DIR . 'includes/class-mcs-cli-alerts.php';
      WP_CLI::add_command( 'mcs mappings', 'MCS_Mappings_CLI' );
      WP_CLI::add_command( 'mcs alerts', 'MCS_Alerts_CLI' );
    }

    MCS_Settings::init();
    MCS_Cron::init();
    MCS_CLI::init();

    // Cron hook
    add_action('mcs_sync_event', ['MCS_ICS_Importer', 'run']);
  }

  public static function load_textdomain() {
    load_plugin_textdomain('minpaku-channel-sync', false, dirname(plugin_basename(MCS_PLUGIN_FILE)) . '/languages');
  }

  public static function maybe_output_ics() {
    $post_id = get_query_var('mcs_ics_post_id');
    if ( $post_id ) {
      MCS_ICS_Exporter::output_ics_for_post( absint($post_id) );
      exit;
    }
  }

}
MCS_Loader::init();
