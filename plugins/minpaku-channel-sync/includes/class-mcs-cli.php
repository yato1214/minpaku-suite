<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * WP-CLI Commands for Minpaku Channel Sync
 */
class MCS_CLI {

  public static function init() {
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
      return;
    }

    WP_CLI::add_command( 'mcs mappings', [ __CLASS__, 'mappings_command' ] );
    WP_CLI::add_command( 'mcs sync', [ __CLASS__, 'sync_command' ] );
    WP_CLI::add_command( 'mcs logs', [ __CLASS__, 'logs_command' ] );
  }

  /**
   * List ICS URL mappings
   *
   * ## OPTIONS
   *
   * [--format=<format>]
   * : Output format (table, json, csv, yaml, etc.)
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - csv
   *   - yaml
   * ---
   *
   * ## EXAMPLES
   *
   *     wp mcs mappings list
   *     wp mcs mappings list --format=json
   */
  public static function mappings_command( $args, $assoc_args ) {
    $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

    $settings = MCS_Settings::get();
    $mappings = $settings['mappings'] ?? [];

    if ( empty( $mappings ) ) {
      WP_CLI::warning( 'No mappings found.' );
      return;
    }

    // Prepare data for output
    $output_data = [];
    foreach ( $mappings as $index => $mapping ) {
      $post = get_post( $mapping['post_id'] );
      $post_title = $post ? $post->post_title : __( '(Post not found)', 'minpaku-channel-sync' );
      $post_status = $post ? $post->post_status : 'not_found';

      $output_data[] = [
        'index' => $index + 1,
        'url' => $mapping['url'],
        'post_id' => $mapping['post_id'],
        'post_title' => $post_title,
        'post_status' => $post_status
      ];
    }

    WP_CLI\Utils\format_items( $format, $output_data, [ 'index', 'url', 'post_id', 'post_title', 'post_status' ] );
  }

  /**
   * Run ICS sync
   *
   * ## OPTIONS
   *
   * [--url=<url>]
   * : Sync specific URL only
   *
   * [--post_id=<id>]
   * : Sync mappings for specific post ID only
   *
   * [--all]
   * : Sync all mappings (default behavior)
   *
   * ## EXAMPLES
   *
   *     wp mcs sync
   *     wp mcs sync --all
   *     wp mcs sync --url="https://example.com/calendar.ics"
   *     wp mcs sync --post_id=123
   */
  public static function sync_command( $args, $assoc_args ) {
    $url = WP_CLI\Utils\get_flag_value( $assoc_args, 'url' );
    $post_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_id' );
    $all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

    $settings = MCS_Settings::get();
    $mappings = $settings['mappings'] ?? [];

    if ( empty( $mappings ) ) {
      WP_CLI::error( 'No mappings configured.' );
    }

    // Filter mappings based on options
    $filtered_mappings = $mappings;

    if ( $url ) {
      $filtered_mappings = array_filter( $mappings, function( $mapping ) use ( $url ) {
        return $mapping['url'] === $url;
      });
      if ( empty( $filtered_mappings ) ) {
        WP_CLI::error( sprintf( 'No mapping found for URL: %s', $url ) );
      }
    }

    if ( $post_id ) {
      $post_id = absint( $post_id );
      $filtered_mappings = array_filter( $mappings, function( $mapping ) use ( $post_id ) {
        return $mapping['post_id'] === $post_id;
      });
      if ( empty( $filtered_mappings ) ) {
        WP_CLI::error( sprintf( 'No mapping found for Post ID: %d', $post_id ) );
      }
    }

    WP_CLI::log( sprintf( 'Starting sync for %d mapping(s)...', count( $filtered_mappings ) ) );

    $results = [
      'total' => [
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'skipped_not_modified' => 0,
        'errors' => 0
      ],
      'by_url' => []
    ];

    foreach ( $filtered_mappings as $mapping ) {
      $mapping_url = $mapping['url'];
      $mapping_post_id = $mapping['post_id'];

      WP_CLI::log( sprintf( 'Processing: %s -> Post ID %d', $mapping_url, $mapping_post_id ) );

      try {
        // Simulate the sync process (using the actual importer logic)
        $sync_result = self::sync_single_mapping( $mapping_url, $mapping_post_id );

        $results['by_url'][$mapping_url] = $sync_result;
        $results['total']['added'] += $sync_result['added'];
        $results['total']['updated'] += $sync_result['updated'];
        $results['total']['skipped'] += $sync_result['skipped'];
        $results['total']['skipped_not_modified'] += $sync_result['skipped_not_modified'];
        $results['total']['errors'] += $sync_result['errors'];

      } catch ( Exception $e ) {
        $results['total']['errors']++;
        $results['by_url'][$mapping_url] = [
          'added' => 0,
          'updated' => 0,
          'skipped' => 0,
          'skipped_not_modified' => 0,
          'errors' => 1,
          'error_message' => $e->getMessage()
        ];
        WP_CLI::warning( sprintf( 'Error processing %s: %s', $mapping_url, $e->getMessage() ) );
      }
    }

    // Output results in table format
    WP_CLI::log( "\n" . WP_CLI::colorize( '%GSync Results:%n' ) );

    $table_data = [];
    foreach ( $results['by_url'] as $url => $stats ) {
      $table_data[] = [
        'url' => substr( $url, 0, 50 ) . ( strlen( $url ) > 50 ? '...' : '' ),
        'added' => $stats['added'],
        'updated' => $stats['updated'],
        'skipped' => $stats['skipped'],
        'skipped_not_modified' => $stats['skipped_not_modified'],
        'errors' => $stats['errors']
      ];
    }

    // Add total row
    $table_data[] = [
      'url' => WP_CLI::colorize( '%YTOTAL%n' ),
      'added' => $results['total']['added'],
      'updated' => $results['total']['updated'],
      'skipped' => $results['total']['skipped'],
      'skipped_not_modified' => $results['total']['skipped_not_modified'],
      'errors' => $results['total']['errors']
    ];

    WP_CLI\Utils\format_items( 'table', $table_data, [ 'url', 'added', 'updated', 'skipped', 'skipped_not_modified', 'errors' ] );

    // Store results for potential later use
    set_transient( 'mcs_last_sync_results', $results, HOUR_IN_SECONDS );

    if ( $results['total']['errors'] > 0 ) {
      WP_CLI::warning( sprintf( 'Sync completed with %d error(s).', $results['total']['errors'] ) );
    } else {
      WP_CLI::success( 'Sync completed successfully.' );
    }
  }

  /**
   * View logs
   *
   * ## OPTIONS
   *
   * [--tail=<number>]
   * : Number of recent log entries to show
   * ---
   * default: 50
   * ---
   *
   * [--level=<level>]
   * : Filter by log level
   * ---
   * default: all
   * options:
   *   - all
   *   - info
   *   - warning
   *   - error
   * ---
   *
   * ## EXAMPLES
   *
   *     wp mcs logs
   *     wp mcs logs --tail=10
   *     wp mcs logs --level=error
   *     wp mcs logs --tail=20 --level=warning
   */
  public static function logs_command( $args, $assoc_args ) {
    $tail = absint( WP_CLI\Utils\get_flag_value( $assoc_args, 'tail', 50 ) );
    $level = WP_CLI\Utils\get_flag_value( $assoc_args, 'level', 'all' );

    if ( ! class_exists( 'MCS_Logger' ) ) {
      WP_CLI::error( 'MCS_Logger class not found.' );
    }

    $logs = MCS_Logger::get_logs( $tail );

    if ( empty( $logs ) ) {
      WP_CLI::warning( 'No logs found.' );
      return;
    }

    // Filter by level if specified
    if ( $level !== 'all' ) {
      $level = strtoupper( $level );
      $logs = array_filter( $logs, function( $log ) use ( $level ) {
        return $log['level'] === $level;
      });

      if ( empty( $logs ) ) {
        WP_CLI::warning( sprintf( 'No logs found for level: %s', $level ) );
        return;
      }
    }

    // Prepare data for output
    $output_data = [];
    foreach ( $logs as $log ) {
      $context_str = '';
      if ( ! empty( $log['context'] ) && is_array( $log['context'] ) ) {
        $context_parts = [];
        foreach ( $log['context'] as $key => $value ) {
          if ( is_scalar( $value ) ) {
            $context_parts[] = "$key: $value";
          }
        }
        $context_str = implode( ', ', $context_parts );
      }

      $output_data[] = [
        'time' => $log['time'],
        'level' => $log['level'],
        'message' => $log['message'],
        'context' => $context_str
      ];
    }

    WP_CLI\Utils\format_items( 'table', $output_data, [ 'time', 'level', 'message', 'context' ] );

    WP_CLI::log( sprintf( "\nShowing %d log entries (filtered by level: %s)", count( $output_data ), $level ) );
  }

  /**
   * Sync a single mapping (helper method)
   *
   * @param string $url
   * @param int $post_id
   * @return array
   */
  private static function sync_single_mapping( $url, $post_id ) {
    if ( ! class_exists( 'MCS_ICS_Importer' ) ) {
      throw new Exception( 'MCS_ICS_Importer class not found' );
    }

    // Validate post exists
    $post = get_post( $post_id );
    if ( ! $post ) {
      throw new Exception( sprintf( 'Post ID %d not found', $post_id ) );
    }

    // Get existing slots for comparison
    $existing_slots = get_post_meta( $post_id, 'mcs_booked_slots', true );
    $existing_slots = is_array( $existing_slots ) ? $existing_slots : [];
    $existing_count = count( $existing_slots );

    // Fetch ICS data
    $response = wp_remote_get( $url, [
      'timeout' => 30,
      'user-agent' => 'Minpaku Channel Sync/CLI'
    ]);

    if ( is_wp_error( $response ) ) {
      throw new Exception( sprintf( 'Failed to fetch ICS: %s', $response->get_error_message() ) );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
      throw new Exception( sprintf( 'HTTP %d error fetching ICS', $status_code ) );
    }

    $ics_content = wp_remote_retrieve_body( $response );
    if ( empty( $ics_content ) ) {
      throw new Exception( 'Empty ICS content received' );
    }

    // Parse ICS content and extract events
    $events = self::parse_ics_events( $ics_content );

    // Process events and update post meta
    $new_slots = [];
    $added = 0;
    $updated = 0;
    $skipped = 0;
    $skipped_not_modified = 0;

    foreach ( $events as $event ) {
      if ( empty( $event['DTSTART'] ) || empty( $event['DTEND'] ) ) {
        $skipped++;
        continue;
      }

      $start_timestamp = strtotime( $event['DTSTART'] );
      $end_timestamp = strtotime( $event['DTEND'] );
      $uid = $event['UID'] ?? '';

      if ( ! $start_timestamp || ! $end_timestamp ) {
        $skipped++;
        continue;
      }

      $new_slot = [ $start_timestamp, $end_timestamp, '', $uid ];

      // Check if this slot already exists
      $found_existing = false;
      foreach ( $existing_slots as $existing_slot ) {
        if ( $existing_slot[0] == $start_timestamp && $existing_slot[1] == $end_timestamp ) {
          $found_existing = true;
          if ( $existing_slot[3] !== $uid ) {
            $updated++;
          } else {
            $skipped++;  // Use skipped for CLI (not skipped_not_modified)
          }
          break;
        }
      }

      if ( ! $found_existing ) {
        $added++;
      }

      $new_slots[] = $new_slot;
    }

    // Update post meta with new slots
    update_post_meta( $post_id, 'mcs_booked_slots', $new_slots );

    MCS_Logger::log( 'INFO', sprintf( 'CLI sync completed for URL %s -> Post %d', $url, $post_id ), [
      'added' => $added,
      'updated' => $updated,
      'skipped' => $skipped,
      'skipped_not_modified' => $skipped_not_modified
    ]);

    return [
      'added' => $added,
      'updated' => $updated,
      'skipped' => $skipped,
      'skipped_not_modified' => $skipped_not_modified,
      'errors' => 0
    ];
  }

  /**
   * Simple ICS parser (helper method)
   *
   * @param string $ics_content
   * @return array
   */
  private static function parse_ics_events( $ics_content ) {
    $events = [];
    $current_event = null;
    $lines = preg_split( '/\r\n|\r|\n/', $ics_content );

    foreach ( $lines as $line ) {
      $line = trim( $line );

      if ( $line === 'BEGIN:VEVENT' ) {
        $current_event = [];
      } elseif ( $line === 'END:VEVENT' && $current_event !== null ) {
        $events[] = $current_event;
        $current_event = null;
      } elseif ( $current_event !== null && strpos( $line, ':' ) !== false ) {
        list( $key, $value ) = explode( ':', $line, 2 );
        $current_event[ $key ] = $value;
      }
    }

    return $events;
  }
}