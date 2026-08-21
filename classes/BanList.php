<?php

require_once __DIR__ . '/Database.php';

class BanList {
    const BAN_DURATION = 3600; // seconds
    const MAX_BAN_FAILURES = 3;

    public static function isBanned($ip) {
        if (!$ip) return false;
        $rows = Database::query('SELECT reset_time FROM rate_limit_counters WHERE rl_key = ?', ['ban:' . $ip]);
        if (count($rows) === 0) return false;
        $row = $rows[0];
        $now = self::dbNow();
        if ($row['reset_time'] <= $now) {
            Database::execute('DELETE FROM rate_limit_counters WHERE rl_key = ?', ['ban:' . $ip]);
            return false;
        }
        return true;
    }

    public static function ban($ip) {
        if (!$ip) return;
        $key = 'banfail:' . $ip;
        Database::execute(
            'INSERT INTO rate_limit_counters (rl_key, total_hits, reset_time)
             VALUES (?, 1, DATE_ADD(NOW(), INTERVAL 3600 SECOND))
             ON DUPLICATE KEY UPDATE
               total_hits = IF(reset_time <= NOW(), 1, total_hits + 1),
               reset_time = IF(reset_time <= NOW(), DATE_ADD(NOW(), INTERVAL 3600 SECOND), reset_time)',
            [$key]
        );
        $rows = Database::query('SELECT total_hits FROM rate_limit_counters WHERE rl_key = ?', [$key]);
        if (count($rows) > 0 && (int) $rows[0]['total_hits'] >= self::MAX_BAN_FAILURES) {
            Database::execute(
                'INSERT INTO rate_limit_counters (rl_key, total_hits, reset_time)
                 VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE reset_time = DATE_ADD(NOW(), INTERVAL ? SECOND)',
                ['ban:' . $ip, self::BAN_DURATION, self::BAN_DURATION]
            );
            Database::execute('DELETE FROM rate_limit_counters WHERE rl_key = ?', [$key]);
        }
    }

    public static function reset($ip) {
        if (!$ip) return;
        Database::execute('DELETE FROM rate_limit_counters WHERE rl_key IN (?, ?)', ['ban:' . $ip, 'banfail:' . $ip]);
    }

    private static function dbNow() {
        $rows = Database::query('SELECT NOW() AS now');
        return $rows[0]['now'];
    }
}
