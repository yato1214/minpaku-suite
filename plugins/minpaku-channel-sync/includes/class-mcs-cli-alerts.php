<?php
if ( ! defined('ABSPATH') ) { exit; }

if ( defined('WP_CLI') && WP_CLI ) :

/**
 * Manage Minpaku Channel Sync alerts.
 */
class MCS_Alerts_CLI {

    /**
     * Show alert settings and current counters.
     *
     * ## OPTIONS
     * [--format=<format>]
     * : table|json (default table)
     *
     * ## EXAMPLES
     * wp mcs alerts status
     * wp mcs alerts status --format=json
     *
     * @subcommand status
     */
    public function status( $args, $assoc_args ) {
        $s = MCS_Alerts::settings();
        $state = get_option(MCS_Alerts::STATE_OPT, []);
        $rows = [];
        if (is_array($state)) {
            foreach ($state as $url=>$row) {
                $rows[] = [
                    'url' => (string)$url,
                    'fail_count' => intval($row['fail_count'] ?? 0),
                    'last_failed_at' => (string)($row['last_failed_at'] ?? ''),
                    'last_notified_at' => (string)($row['last_notified_at'] ?? ''),
                ];
            }
        }
        $format = $assoc_args['format'] ?? 'table';
        if ($format === 'json') {
            \WP_CLI::print_value(['settings'=>$s, 'state'=>$rows], ['format'=>'json']);
        } else {
            \WP_CLI::log('Settings: enabled='.($s['enabled']?'1':'0').', threshold='.$s['threshold'].', cooldown='.$s['cooldown_hours'].'h, recipient='.$s['recipient']);
            \WP_CLI\Utils\format_items('table', $rows, ['url','fail_count','last_failed_at','last_notified_at']);
        }
    }

    /**
     * Backward-compat: `wp mcs alerts` だけでも status を表示
     */
    public function __invoke( $args, $assoc_args ) {
        return $this->status( $args, $assoc_args );
    }
}

endif;