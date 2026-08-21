<?php

require_once __DIR__ . '/Database.php';

class Unblock {
    public static function unblockIp($ip) {
        if (!$ip) return;
        try {
            Database::execute('DELETE FROM rate_limit_counters WHERE rl_key = ? OR rl_key = ?', ['rl:' . $ip, 'rl:api:' . $ip]);
            Database::execute('DELETE FROM rate_limit_counters WHERE rl_key LIKE ?', ['%:' . $ip]);
            BanList::reset($ip);
        } catch (Throwable $e) {}
    }
}
