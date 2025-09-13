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
    try {
      $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

      if ( ! class_exists( 'MCS_Settings' ) ) {
        WP_CLI::error( 'MCS_Settings class not found. Plugin may not be activated.' );
      }

      $settings = MCS_Settings::get();
      $mappings = $settings['mappings'] ?? [];

      if ( empty( $mappings ) ) {
        WP_CLI::line( 'No mappings found.' );
        return;
      }

      // Prepare data for output
      $output_data = [];
      foreach ( $mappings as $index => $mapping ) {
        $post = get_post( $mapping['post_id'] );
        $post_title = $post ? $post->post_title : '(Post not found)';
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

    } catch ( Exception $e ) {
      WP_CLI::error( sprintf( 'Error listing mappings: %s', $e->getMessage() ) );
    }
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
    try {
      $url = WP_CLI\Utils\get_flag_value( $assoc_args, 'url' );
      $post_id = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_id' );
      $all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

      if ( ! class_exists( 'MCS_Settings' ) ) {
        WP_CLI::error( 'MCS_Settings class not found. Plugin may not be activated.' );
      }

      if ( ! class_exists( 'MCS_ICS_Importer' ) ) {
        WP_CLI::error( 'MCS_ICS_Importer class not found. Plugin may not be activated.' );
      }

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

      WP_CLI::line( sprintf( 'Starting sync for %d mapping(s)...', count( $filtered_mappings ) ) );

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

        WP_CLI::line( sprintf( 'Processing: %s -> Post ID %d', $mapping_url, $mapping_post_id ) );

        try {
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
          WP_CLI::line( sprintf( 'Error processing %s: %s', $mapping_url, $e->getMessage() ) );
        }
      }

      // Output results in table format
      WP_CLI::line( '' );
      WP_CLI::line( 'Sync Results:' );

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
        'url' => 'TOTAL',
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
        WP_CLI::error( sprintf( 'Sync completed with %d error(s).', $results['total']['errors'] ) );
      } else {
        WP_CLI::success( 'Sync completed successfully.' );
      }

    } catch ( Exception $e ) {
      WP_CLI::error( sprintf( 'Error during sync: %s', $e->getMessage() ) );
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
    try {
      $tail = absint( WP_CLI\Utils\get_flag_value( $assoc_args, 'tail', 50 ) );
      $level = WP_CLI\Utils\get_flag_value( $assoc_args, 'level', 'all' );

      if ( ! class_exists( 'MCS_Logger' ) ) {
        WP_CLI::error( 'MCS_Logger class not found. Plugin may not be activated.' );
      }

      $logs = MCS_Logger::get_logs( $tail );

      if ( empty( $logs ) ) {
        WP_CLI::line( 'No logs found.' );
        return;
      }

      // Filter by level if specified
      if ( $level !== 'all' ) {
        $level = strtoupper( $level );
        $logs = array_filter( $logs, function( $log ) use ( $level ) {
          return $log['level'] === $level;
        });

        if ( empty( $logs ) ) {
          WP_CLI::line( sprintf( 'No logs found for level: %s', $level ) );
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

      WP_CLI::line( sprintf( "\nShowing %d log entries (filtered by level: %s)", count( $output_data ), $level ) );

    } catch ( Exception $e ) {
      WP_CLI::error( sprintf( 'Error retrieving logs: %s', $e->getMessage() ) );
    }
  }

  /**
   * Sync a single mapping (helper method)
   *
   * @param string $url
   * @param int $post_id
   * @return array
   * @throws Exception
   */
  private static function sync_single_mapping( $url, $post_id ) {
    // Validate post exists
    $post = get_post( $post_id );
    if ( ! $post ) {
      throw new Exception( sprintf( 'Post ID %d not found', $post_id ) );
    }

    // Get existing slots for comparison
    $existing_slots = get_post_meta( $post_id, 'mcs_booked_slots', true );
    $existing_slots = is_array( $existing_slots ) ? $existing_slots : [];

    // Fetch ICS data using the same logic as the main importer
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

    // Parse ICS content using existing parser
    if ( ! class_exists( 'MCS_ICS_Importer' ) ) {
      throw new Exception( 'MCS_ICS_Importer class not found' );
    }

    // Use reflection to access private parse_ics method
    $reflection = new ReflectionClass( 'MCS_ICS_Importer' );
    $parse_method = $reflection->getMethod( 'parse_ics' );
    $parse_method->setAccessible( true );
    $events = $parse_method->invokeArgs( null, [ $ics_content ] );

    if ( empty( $events ) ) {
      return [
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'skipped_not_modified' => 0,
        'errors' => 0
      ];
    }

    // Use reflection to access private apply_to_post method
    $apply_method = $reflection->getMethod( 'apply_to_post' );
    $apply_method->setAccessible( true );
    $result = $apply_method->invokeArgs( null, [ $post_id, $events ] );

    // Log the CLI sync
    if ( class_exists( 'MCS_Logger' ) ) {
      MCS_Logger::log( 'INFO', sprintf( 'CLI sync completed for URL %s -> Post %d', $url, $post_id ), [
        'added' => $result['added'],
        'updated' => $result['updated'],
        'skipped' => $result['skipped']
      ]);
    }

    return [
      'added' => $result['added'],
      'updated' => $result['updated'],
      'skipped' => $result['skipped'],
      'skipped_not_modified' => 0, // CLI doesn't use conditional requests
      'errors' => 0
    ];
  }
}