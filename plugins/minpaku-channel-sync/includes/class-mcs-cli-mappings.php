<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined( 'WP_CLI' ) && WP_CLI ) :

/**
 * Manage Minpaku Channel Sync mappings.
 */
class MCS_Mappings_CLI {

    /**
     * List mappings.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. One of: table, json. Default: table.
     *
     * ## EXAMPLES
     *     wp mcs mappings list
     *     wp mcs mappings list --format=json
     *
     * @subcommand list
     */
    public function list_( $args, $assoc_args ) {
        $opt = get_option( 'mcs_settings', [] );
        $maps = ( is_array( $opt ) && isset( $opt['mappings'] ) && is_array( $opt['mappings'] ) ) ? $opt['mappings'] : [];

        $rows = [];
        foreach ( $maps as $m ) {
            $rows[] = [
                'url'     => isset( $m['url'] ) ? (string) $m['url'] : '',
                'post_id' => isset( $m['post_id'] ) ? (int) $m['post_id'] : 0,
            ];
        }

        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
        if ( $format === 'json' ) {
            \WP_CLI::print_value( $rows, [ 'format' => 'json' ] );
            return;
        }
        \WP_CLI\Utils\format_items( 'table', $rows, [ 'url', 'post_id' ] );
    }

    /**
     * Backward-compat: `wp mcs mappings` だけでも list を表示
     */
    public function __invoke( $args, $assoc_args ) {
        return $this->list_( $args, $assoc_args );
    }
}

endif;