<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BanList.php';
require_once __DIR__ . '/Unblock.php';

class Captcha {
    const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const LENGTH = 6;
    const TTL = 300; // seconds

    private static $tableCreated = false;

    public static function initTable() {
        if (self::$tableCreated) return;
        self::$tableCreated = true;
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS captcha_challenges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    code VARCHAR(10) NOT NULL,
                    attempts INT DEFAULT 0,
                    used TINYINT(1) DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('Captcha table init error: ' . $e->getMessage());
        }
    }

    public static function generate() {
        self::initTable();
        $token = bin2hex(random_bytes(16));
        $code = self::generateCode();
        Database::execute(
            'INSERT INTO captcha_challenges (token, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))',
            [$token, $code, self::TTL]
        );
        return ['token' => $token, 'image' => self::generateSvg($code)];
    }

    private static function generateCode() {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        return $code;
    }

    private static function generateSvg($code) {
        $chars = str_split($code);
        $colors = ['#22c55e', '#FF7E00', '#2563eb', '#16a34a', '#dc2626', '#7c3aed', '#ea580c', '#0891b2', '#c026d3'];
        $w = 220; $h = 70;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
        // Gradient background
        $svg .= '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#f8fafc"/><stop offset="100%" stop-color="#e2e8f0"/></linearGradient></defs>';
        $svg .= '<rect width="' . $w . '" height="' . $h . '" fill="url(#bg)" rx="10"/>';
        // Noise lines (curved and straight)
        for ($i = 0; $i < 8; $i++) {
            $x1 = 0; $y1 = number_format(5 + mt_rand(0, 600) / 10, 1);
            $x2 = $w; $y2 = number_format(5 + mt_rand(0, 600) / 10, 1);
            $c = $colors[mt_rand(0, count($colors) - 1)];
            $svg .= '<line x1="0" y1="' . $y1 . '" x2="' . $w . '" y2="' . $y2 . '" stroke="' . $c . '" stroke-width="1" opacity="0.15"/>';
            // Bezier curve
            $cx = number_format(mt_rand(20, 2000) / 10, 1);
            $cy = number_format(mt_rand(50, 650) / 10, 1);
            $svg .= '<path d="M 0 ' . $y1 . ' Q ' . $cx . ' ' . $cy . ' ' . $w . ' ' . $y2 . '" stroke="' . $c . '" stroke-width="0.8" fill="none" opacity="0.1"/>';
        }
        // Background grid pattern
        for ($i = 0; $i < 20; $i++) {
            $x = number_format(mt_rand(0, 2200) / 10, 1);
            $y = number_format(mt_rand(0, 700) / 10, 1);
            $c = $colors[mt_rand(0, count($colors) - 1)];
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="0.5" fill="' . $c . '" opacity="0.08"/>';
        }
        // Character rendering with heavy distortion
        $cw = $w / (count($chars) + 1.5);
        foreach ($chars as $i => $ch) {
            $x = number_format($cw * ($i + 1 + mt_rand(-20, 20) / 100), 1);
            $y = number_format(28 + mt_rand(0, 250) / 10, 1);
            $rot = mt_rand(-25, 25);
            $col = $colors[mt_rand(0, count($colors) - 1)];
            $fs = 22 + mt_rand(0, 8);
            // Text shadow for depth
            $svg .= '<text x="' . number_format($x + 1, 1) . '" y="' . number_format($y + 1, 1) . '" fill="#000000" font-family="Courier New,monospace" font-size="' . $fs . '" font-weight="800" transform="rotate(' . $rot . ',' . number_format($x + 1, 1) . ',' . number_format($y + 1, 1) . ')" opacity="0.15">' . $ch . '</text>';
            $svg .= '<text x="' . $x . '" y="' . $y . '" fill="' . $col . '" font-family="Courier New,monospace" font-size="' . $fs . '" font-weight="800" transform="rotate(' . $rot . ',' . $x . ',' . $y . ')">' . $ch . '</text>';
        }
        // Heavy noise dots
        for ($i = 0; $i < 100; $i++) {
            $cx = number_format(mt_rand(0, 2200) / 10, 1);
            $cy = number_format(mt_rand(0, 700) / 10, 1);
            $r = number_format(0.3 + mt_rand(0, 15) / 10, 1);
            $c = $colors[mt_rand(0, count($colors) - 1)];
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $c . '" opacity="0.1"/>';
        }
        // Speckle lines (thin scratches)
        for ($i = 0; $i < 15; $i++) {
            $x1 = number_format(mt_rand(0, 2200) / 10, 1);
            $y1 = number_format(mt_rand(0, 700) / 10, 1);
            $x2 = number_format($x1 + mt_rand(-30, 30) / 10, 1);
            $y2 = number_format($y1 + mt_rand(-30, 30) / 10, 1);
            $c = $colors[mt_rand(0, count($colors) - 1)];
            $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $c . '" stroke-width="0.4" opacity="0.08"/>';
        }
        $svg .= '</svg>';
        return base64_encode($svg);
    }

    public static function verify($captchaToken, $captchaCode) {
        if (!$captchaToken || !$captchaCode) return ['valid' => false, 'error' => 'Captcha required'];
        self::initTable();

        $rows = Database::query('SELECT id, code, attempts, used, expires_at FROM captcha_challenges WHERE token = ?', [$captchaToken]);
        if (count($rows) === 0) return ['valid' => false, 'error' => 'Invalid captcha. Please refresh and try again.'];
        $challenge = $rows[0];

        if ($challenge['used']) {
            Database::execute('DELETE FROM captcha_challenges WHERE id = ?', [$challenge['id']]);
            return ['valid' => false, 'error' => 'Captcha already used. Please refresh.'];
        }

        $nowRows = Database::query('SELECT NOW() AS now');
        $now = $nowRows[0]['now'];
        if ($challenge['expires_at'] < $now) {
            Database::execute('DELETE FROM captcha_challenges WHERE id = ?', [$challenge['id']]);
            return ['valid' => false, 'error' => 'Captcha expired. Please refresh.'];
        }

        if ((int) $challenge['attempts'] >= 5) {
            Database::execute('DELETE FROM captcha_challenges WHERE id = ?', [$challenge['id']]);
            return ['valid' => false, 'error' => 'Too many failed captcha attempts. Please refresh.'];
        }

        if (strtoupper($captchaCode) !== $challenge['code']) {
            Database::execute('UPDATE captcha_challenges SET attempts = attempts + 1 WHERE id = ?', [$challenge['id']]);
            return ['valid' => false, 'error' => 'Incorrect captcha. Please try again.'];
        }

        Database::execute('UPDATE captcha_challenges SET used = 1 WHERE id = ?', [$challenge['id']]);
        return ['valid' => true];
    }

    public static function unblock($captchaToken, $captchaCode, $ip) {
        $result = self::verify($captchaToken, $captchaCode);
        if (!$result['valid']) {
            return ['success' => false, 'error' => $result['error']];
        }
        BanList::reset($ip);
        Unblock::unblockIp($ip);
        return ['success' => true, 'message' => 'Access restored.'];
    }
}
