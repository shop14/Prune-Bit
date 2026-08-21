<?php

require_once __DIR__ . '/Database.php';

class Session {
    private static $countryCache = [];
    private static $migrationDone = false;
    private static $loginLogTableReady = false;

    public static function ensureLoginLogTable() {
        if (self::$loginLogTableReady) return;
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS login_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    wallet_id VARCHAR(128) NOT NULL,
                    ip VARCHAR(45) NULL,
                    country VARCHAR(100) NULL,
                    user_agent VARCHAR(512) NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_login_log_created_at (created_at),
                    KEY idx_login_log_wallet_id (wallet_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('Failed to ensure login_log table: ' . $e->getMessage());
        }
        self::$loginLogTableReady = true;
    }

    public static function ensureTrackingColumns() {
        if (self::$migrationDone) return;
        try {
            $cols = Database::query('SHOW COLUMNS FROM sessions');
            $existing = array_map(function ($c) { return $c['Field']; }, $cols);
            $toAdd = [];
            if (!in_array('ip', $existing)) $toAdd[] = 'ip VARCHAR(45) NULL';
            if (!in_array('user_agent', $existing)) $toAdd[] = 'user_agent VARCHAR(512) NULL';
            if (!in_array('country', $existing)) $toAdd[] = 'country VARCHAR(100) NULL';
            foreach ($toAdd as $def) {
                Database::execute('ALTER TABLE sessions ADD COLUMN ' . $def);
            }
        } catch (Throwable $e) {
            error_log('Failed to ensure sessions tracking columns: ' . $e->getMessage());
        }
        self::$migrationDone = true;
    }

    public static function metaFromRequest() {
        $ip = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        if (!$ip && !empty($_SERVER['REMOTE_ADDR'])) $ip = $_SERVER['REMOTE_ADDR'];
        if ($ip) $ip = preg_replace('/^::ffff:/', '', $ip);
        $userAgent = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        return ['ip' => $ip, 'userAgent' => $userAgent];
    }

    private static function isPrivateIp($ip) {
        if (!$ip) return true;
        $clean = preg_replace('/^::ffff:/', '', $ip);
        if ($clean === '::1' || $clean === '127.0.0.1') return true;
        if (preg_match('/^10\./', $clean) || preg_match('/^192\.168\./', $clean)) return true;
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $clean)) return true;
        if (preg_match('/^100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $clean)) return true;
        if (preg_match('/^0\./', $clean) || preg_match('/^169\.254\./', $clean)) return true;
        return false;
    }

    public static function lookupCountry($ip) {
        if (self::isPrivateIp($ip)) return 'Local';
        if (isset(self::$countryCache[$ip])) return self::$countryCache[$ip];
        $country = 'Unknown';
        $ch = curl_init('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno === 0 && $body) {
            $parsed = json_decode($body, true);
            if (is_array($parsed) && ($parsed['status'] ?? '') === 'success' && !empty($parsed['country'])) {
                $country = $parsed['country'];
            }
        }
        self::$countryCache[$ip] = $country;
        return $country;
    }

    private static function dbNow() {
        $rows = Database::query('SELECT NOW() AS now');
        return $rows[0]['now'];
    }

    public static function create($walletId, $expiresInHours = 24, $meta = []) {
        self::ensureTrackingColumns();
        self::ensureLoginLogTable();
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $now = self::dbNow();
        $expiresAt = date('Y-m-d H:i:s', strtotime($now . ' +' . $expiresInHours . ' hours'));
        $ip = $meta['ip'] ?? null;
        $userAgent = $meta['userAgent'] ?? null;
        $country = $meta['country'] ?? null;

        Database::execute(
            'INSERT INTO sessions (token, wallet_id, expires_at, last_activity, ip, user_agent, country) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tokenHash, $walletId, $expiresAt, $now, $ip, $userAgent, $country]
        );

        if ($ip && $country === null) {
            try {
                $country = self::lookupCountry($ip);
                Database::execute('UPDATE sessions SET country = ? WHERE token = ?', [$country, $tokenHash]);
            } catch (Throwable $e) {}
        }

        try {
            Database::execute(
                'INSERT INTO login_log (wallet_id, ip, country, user_agent) VALUES (?, ?, ?, ?)',
                [$walletId, $ip, $country, $userAgent]
            );
        } catch (Throwable $e) {
            error_log('Failed to record login log: ' . $e->getMessage());
        }

        return $token;
    }

    public static function validate($token, $inactivityTimeoutMinutes = 5) {
        $tokenHash = hash('sha256', $token);
        $rows = Database::query('SELECT * FROM sessions WHERE token = ? AND expires_at > NOW()', [$tokenHash]);
        if (count($rows) === 0) return null;
        $session = $rows[0];

        if (!empty($session['last_activity'])) {
            $nowTs = strtotime(self::dbNow());
            $inactive = ($nowTs - strtotime($session['last_activity'])) / 60;
            if ($inactive > $inactivityTimeoutMinutes) {
                self::destroy($token);
                return null;
            }
        }

        self::updateLastActivity($tokenHash);
        return $session;
    }

    public static function updateLastActivity($tokenHash) {
        Database::execute('UPDATE sessions SET last_activity = NOW() WHERE token = ?', [$tokenHash]);
    }

    public static function destroy($token) {
        $tokenHash = hash('sha256', $token);
        Database::execute('DELETE FROM sessions WHERE token = ?', [$tokenHash]);
    }

    public static function cleanupExpired() {
        Database::execute('DELETE FROM sessions WHERE expires_at < NOW()');
    }
}
