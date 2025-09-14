<?php
if ( ! defined('ABSPATH') ) { exit; }

class MCS_Alerts {
    const STATE_OPT = 'mcs_alert_state'; // per-URL fail counters etc.

    public static function settings() : array {
        $opt = get_option('mcs_settings', []);
        $alerts = is_array($opt) && isset($opt['alerts']) && is_array($opt['alerts']) ? $opt['alerts'] : [];
        return wp_parse_args($alerts, [
            'enabled'        => true,
            'threshold'      => 3,
            'cooldown_hours' => 12,
            'recipient'      => get_option('admin_email'),
        ]);
    }

    public static function record_failure(string $url, string $message=''): void {
        $s = self::settings();
        $state = get_option(self::STATE_OPT, []);
        if (!is_array($state)) { $state = []; }
        $row = isset($state[$url]) && is_array($state[$url]) ? $state[$url] : ['fail_count'=>0];

        $row['fail_count'] = intval($row['fail_count']) + 1;
        $row['last_failed_at'] = current_time('mysql');

        $should_notify = false;
        if ( $s['enabled'] && $row['fail_count'] >= intval($s['threshold']) ) {
            $last = isset($row['last_notified_at']) ? strtotime($row['last_notified_at']) : 0;
            $cool = time() - HOUR_IN_SECONDS * intval($s['cooldown_hours']);
            $should_notify = ($last === 0 || $last < $cool);
        }

        if ($should_notify) {
            $subj = sprintf('[Minpaku Sync] Repeated failures for %s', $url);
            $body = "URL: {$url}\nFailures: {$row['fail_count']}\nLast error: {$message}\nSite: ".home_url()."\nTime: ".current_time('mysql');
            $sent = wp_mail($s['recipient'], $subj, $body);
            $row['last_notified_at'] = current_time('mysql');
            MCS_Logger::warning('Alert email sent', ['url'=>$url, 'recipient'=>$s['recipient'], 'sent'=>$sent ? 1:0, 'fail_count'=>$row['fail_count']]);
        }

        $state[$url] = $row;
        update_option(self::STATE_OPT, $state, false);
    }

    public static function record_success(string $url): void {
        $state = get_option(self::STATE_OPT, []);
        if (!is_array($state)) { return; }
        if ( isset($state[$url]) ) {
            // 成功でカウンタをリセット
            $state[$url]['fail_count'] = 0;
            update_option(self::STATE_OPT, $state, false);
        }
    }
}