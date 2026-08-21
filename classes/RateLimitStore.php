<?php

require_once __DIR__ . '/Database.php';

class RateLimitStore {
    private static $windowMs = 60000;
    private static $tableCreated = false;

    public static function init($options = []) {
        if (!empty($options['windowMs'])) self::$windowMs = (int) $options['windowMs'];
        self::ensureTable();
    }

    private static function ensureTable() {
        if (self::$tableCreated) return;
        self::$tableCreated = true;
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS rate_limit_counters (
                    rl_key VARCHAR(190) NOT NULL PRIMARY KEY,
                    total_hits INT NOT NULL DEFAULT 1,
                    reset_time DATETIME NOT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('RateLimitStore table init error: ' . $e->getMessage());
        }
    }

    private static function fullKey($key) {
        return 'rl:' . $key;
    }

    public static function get($key) {
        try {
            $rows = Database::query('SELECT total_hits, reset_time FROM rate_limit_counters WHERE rl_key = ?', [self::fullKey($key)]);
            if (count($rows) === 0) return null;
            return ['totalHits' => (int) $rows[0]['total_hits'], 'resetTime' => $rows[0]['reset_time']];
        } catch (PDOException $e) {
            if (self::isMissingTable($e)) {
                self::$tableCreated = false;
                self::ensureTable();
                return self::get($key);
            }
            throw $e;
        }
    }

    public static function increment($key) {
        self::ensureTable();
        $windowSeconds = max((int) ceil(self::$windowMs / 1000), 1);
        $fullKey = self::fullKey($key);
        Database::execute(
            'INSERT INTO rate_limit_counters (rl_key, total_hits, reset_time)
             VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
               total_hits = IF(reset_time <= NOW(), 1, total_hits + 1),
               reset_time = IF(reset_time <= NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), reset_time)',
            [$fullKey, $windowSeconds, $windowSeconds]
        );
        $rows = Database::query('SELECT total_hits, reset_time FROM rate_limit_counters WHERE rl_key = ?', [$fullKey]);
        if (count($rows) === 0) {
            return ['totalHits' => 1, 'resetTime' => date('Y-m-d H:i:s', time() + self::$windowMs / 1000)];
        }
        return ['totalHits' => (int) $rows[0]['total_hits'], 'resetTime' => $rows[0]['reset_time']];
    }

    public static function decrement($key) {
        Database::execute('UPDATE rate_limit_counters SET total_hits = GREATEST(total_hits - 1, 0) WHERE rl_key = ?', [self::fullKey($key)]);
    }

    public static function resetKey($key) {
        Database::execute('DELETE FROM rate_limit_counters WHERE rl_key = ?', [self::fullKey($key)]);
    }

    public static function resetAll() {
        Database::execute('DELETE FROM rate_limit_counters');
    }

    private static function isMissingTable(PDOException $e) {
        $code = $e->getCode();
        return $code === '42S02' || $code === '1146' || stripos($e->getMessage(), 'doesn\'t exist') !== false;
    }
}
