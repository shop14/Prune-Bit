<?php

Api::requirePost();

try {
    $token = isset($_COOKIE['wallet_token']) ? $_COOKIE['wallet_token'] : Api::body('token');
    if ($token) {
        Session::destroy($token);
    }
    setcookie('wallet_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== ''),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    error_log('Logout error: ' . $e->getMessage());
    jsonResponse(['error' => 'Authentication failed'], 500);
}
