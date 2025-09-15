<?php
/**
 * WP-CLI Commands for Minpaku Suite
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI commands for Minpaku Suite
 */
class MCS_CLI {

    /**
     * Initialize WP-CLI commands
     */
    public static function init() {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('mcs mappings', [__CLASS__, 'mappings_command']);
            WP_CLI::add_command('mcs sync', [__CLASS__, 'sync_command']);
        }
    }

    /**
     * Manage property mappings
     *
     * ## SUBCOMMANDS
     *
     * * regen - Regenerate all property mappings
     * * list - List all current mappings
     *
     * ## EXAMPLES
     *
     *     wp mcs mappings regen
     *     wp mcs mappings list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function mappings_command($args, $assoc_args) {
        $subcommand = isset($args[0]) ? $args[0] : '';

        switch ($subcommand) {
            case 'regen':
                self::regenerate_mappings($assoc_args);
                break;

            case 'list':
                self::list_mappings($assoc_args);
                break;

            default:
                WP_CLI::error('Unknown subcommand. Use: regen, list');
        }
    }

    /**
     * Synchronization commands
     *
     * ## SUBCOMMANDS
     *
     * * all - Sync all configured mappings
     * * single - Sync a single property by ID
     *
     * ## OPTIONS
     *
     * [--post-id=<id>]
     * : Property post ID (required for single command)
     *
     * [--dry-run]
     * : Show what would be synced without making changes
     *
     * ## EXAMPLES
     *
     *     wp mcs sync all
     *     wp mcs sync single --post-id=123
     *     wp mcs sync all --dry-run
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function sync_command($args, $assoc_args) {
        $subcommand = isset($args[0]) ? $args[0] : '';

        switch ($subcommand) {
            case 'all':
                self::sync_all($assoc_args);
                break;

            case 'single':
                self::sync_single($assoc_args);
                break;

            default:
                WP_CLI::error('Unknown subcommand. Use: all, single');
        }
    }

    /**
     * Regenerate all property mappings
     *
     * @param array $assoc_args
     */
    private static function regenerate_mappings($assoc_args) {
        WP_CLI::log('Regenerating property mappings...');

        $properties = get_posts([
            'post_type' => 'property',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        if (empty($properties)) {
            WP_CLI::warning('No published properties found.');
            return;
        }

        $mappings = [];
        $progress = WP_CLI\Utils\make_progress_bar('Processing properties', count($properties));

        foreach ($properties as $post_id) {
            $ics_key = get_post_meta($post_id, '_ics_key', true);

            if (empty($ics_key)) {
                $ics_key = wp_generate_password(24, false, false);
                update_post_meta($post_id, '_ics_key', $ics_key);
                WP_CLI::debug("Generated new ICS key for property {$post_id}: {$ics_key}");
            }

            $ics_url = home_url("ics/property/{$post_id}/{$ics_key}.ics");

            $mappings[] = [
                'url' => $ics_url,
                'post_id' => $post_id,
            ];

            $progress->tick();
        }

        $progress->finish();

        // Update settings
        $settings = wp_parse_args(get_option('mcs_settings', []), [
            'export_disposition' => 'inline',
            'flush_on_save' => false,
            'alerts' => [
                'enabled' => false,
                'threshold' => 1,
                'cooldown_hours' => 24,
                'recipient' => get_option('admin_email'),
            ],
            'mappings' => [],
        ]);

        $settings['mappings'] = $mappings;
        update_option('mcs_settings', $settings);

        WP_CLI::success(sprintf('Successfully regenerated %d property mappings.', count($mappings)));

        // Display some examples
        if (count($mappings) > 0) {
            WP_CLI::log('');
            WP_CLI::log('Example URLs generated:');
            $examples = array_slice($mappings, 0, 3);
            foreach ($examples as $mapping) {
                $title = get_the_title($mapping['post_id']);
                WP_CLI::log("  - {$title} (ID: {$mapping['post_id']}): {$mapping['url']}");
            }

            if (count($mappings) > 3) {
                WP_CLI::log(sprintf('  ... and %d more', count($mappings) - 3));
            }
        }
    }

    /**
     * List all current mappings
     *
     * @param array $assoc_args
     */
    private static function list_mappings($assoc_args) {
        $settings = get_option('mcs_settings', []);
        $mappings = isset($settings['mappings']) ? $settings['mappings'] : [];

        if (empty($mappings)) {
            WP_CLI::warning('No mappings found. Run "wp mcs mappings regen" to generate them.');
            return;
        }

        WP_CLI::log(sprintf('Found %d property mappings:', count($mappings)));
        WP_CLI::log('');

        $table_data = [];
        foreach ($mappings as $mapping) {
            $post = get_post($mapping['post_id']);
            $title = $post ? get_the_title($post) : '(Not found)';
            $status = $post ? $post->post_status : 'deleted';

            $table_data[] = [
                'ID' => $mapping['post_id'],
                'Title' => wp_trim_words($title, 8),
                'Status' => $status,
                'URL' => $mapping['url'],
            ];
        }

        WP_CLI\Utils\format_items('table', $table_data, ['ID', 'Title', 'Status', 'URL']);
    }

    /**
     * Sync all configured mappings
     *
     * @param array $assoc_args
     */
    private static function sync_all($assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);

        if ($dry_run) {
            WP_CLI::log('DRY RUN: Showing what would be synced (no changes will be made)');
        }

        WP_CLI::log('Starting sync for all mappings...');

        if (!class_exists('MCS_Sync')) {
            WP_CLI::error('MCS_Sync class not found. Core sync functionality may not be loaded.');
        }

        try {
            if ($dry_run) {
                // For dry run, just list what would be synced
                $settings = get_option('mcs_settings', []);
                $mappings = isset($settings['mappings']) ? $settings['mappings'] : [];

                if (empty($mappings)) {
                    WP_CLI::warning('No mappings configured for sync.');
                    return;
                }

                WP_CLI::log(sprintf('Would sync %d mappings:', count($mappings)));
                foreach ($mappings as $mapping) {
                    $title = get_the_title($mapping['post_id']);
                    WP_CLI::log("  - {$title} (ID: {$mapping['post_id']})");
                }
            } else {
                $results = MCS_Sync::sync_all();

                WP_CLI::success('Sync completed!');
                WP_CLI::log(sprintf(
                    'Results: %d added, %d updated, %d skipped, %d not modified, %d errors',
                    $results['added'],
                    $results['updated'],
                    $results['skipped'],
                    $results['skipped_not_modified'],
                    $results['errors']
                ));

                if ($results['errors'] > 0) {
                    WP_CLI::warning('Some errors occurred during sync. Check logs for details.');
                }
            }

        } catch (Exception $e) {
            WP_CLI::error('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync a single property
     *
     * @param array $assoc_args
     */
    private static function sync_single($assoc_args) {
        if (!isset($assoc_args['post-id'])) {
            WP_CLI::error('--post-id parameter is required for single sync.');
        }

        $post_id = absint($assoc_args['post-id']);
        $dry_run = isset($assoc_args['dry-run']);

        if (!$post_id) {
            WP_CLI::error('Invalid post ID provided.');
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'property') {
            WP_CLI::error('Post not found or is not a property.');
        }

        WP_CLI::log(sprintf('Syncing property: %s (ID: %d)', get_the_title($post), $post_id));

        if ($dry_run) {
            WP_CLI::log('DRY RUN: Would sync this property (no changes will be made)');
            return;
        }

        // Find the mapping for this property
        $settings = get_option('mcs_settings', []);
        $mappings = isset($settings['mappings']) ? $settings['mappings'] : [];

        $mapping = null;
        foreach ($mappings as $m) {
            if ($m['post_id'] == $post_id) {
                $mapping = $m;
                break;
            }
        }

        if (!$mapping) {
            WP_CLI::error('No mapping found for this property. Run "wp mcs mappings regen" first.');
        }

        if (!class_exists('MCS_Sync')) {
            WP_CLI::error('MCS_Sync class not found. Core sync functionality may not be loaded.');
        }

        try {
            // For single sync, we'll use a hypothetical external URL (this would be configured in real usage)
            WP_CLI::warning('Single property sync requires an external ICS URL to be configured.');
            WP_CLI::log('This command is mainly for syncing FROM external sources TO WordPress.');
            WP_CLI::log('The generated ICS URL is: ' . $mapping['url']);

        } catch (Exception $e) {
            WP_CLI::error('Sync failed: ' . $e->getMessage());
        }
    }
}