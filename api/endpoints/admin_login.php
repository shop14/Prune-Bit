<?php

Api::requirePost();

try {
    $username = Api::body('username');
    $password = Api::body('password');
    $captchaToken = Api::body('captcha_token');
    $captchaCode = Api::body('captcha_code');

    if (!$username || !$password) {
        jsonResponse(['error' => 'Username and password required'], 400);
    }
    if (!$captchaToken || !$captchaCode) {
        jsonResponse(['error' => 'Captcha required'], 400);
    }

    Database::execute(
        'CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(128) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )'
    );
    Database::execute(
        'CREATE TABLE IF NOT EXISTS admin_sessions (
            token VARCHAR(64) PRIMARY KEY,
            username VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $captcha = Captcha::verify($captchaToken, $captchaCode);
    if (!$captcha['valid']) {
        $status = $captcha['error'] === 'Too many failed captcha attempts. Please refresh.' ? 429 : 400;
        jsonResponse(['error' => $captcha['error']], $status);
    }

    $settings = Database::query("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash'");
    if (count($settings) === 0) {
        jsonResponse(['error' => 'No admin account configured'], 401);
    }

    $adminPasswordValid = password_verify($password, $settings[0]['setting_value']);
    $expectedUsername = env('ADMIN_USERNAME') ?: 'admin';

    if ($username === $expectedUsername && $adminPasswordValid) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        Database::execute(
            'INSERT INTO admin_sessions (token, username, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))',
            [$tokenHash, $expectedUsername]
        );
        jsonResponse(['success' => true, 'token' => $token]);
    } else {
        jsonResponse(['error' => 'Invalid admin credentials'], 401);
    }
} catch (Throwable $e) {
    error_log('Admin login error: ' . $e->getMessage());
    jsonResponse(['error' => 'Authentication failed'], 500);
}
