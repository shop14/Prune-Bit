<?php

Api::requirePost();

$endpointPath = isset($GLOBALS['api_path']) ? $GLOBALS['api_path'] : '';

if (strpos($endpointPath, 'verify-captcha') !== false) {
    try {
        $captchaToken = Api::body('captcha_token');
        $captchaCode = Api::body('captcha_code');
        $result = Captcha::verify($captchaToken, $captchaCode);
        if (!$result['valid']) {
            jsonResponse(['error' => $result['error']], 400);
        }
        jsonResponse(['success' => true]);
    } catch (Throwable $e) {
        error_log('Captcha verify error: ' . $e->getMessage());
        jsonResponse(['error' => 'Captcha verification failed.'], 500);
    }
}

try {
    $password = Api::body('password');

    if (!$password) {
        jsonResponse(['error' => 'PIN is required to create a wallet.'], 400);
    }

    if (strlen($password) < 4) {
        jsonResponse(['error' => 'PIN must be at least 4 characters long.'], 400);
    }

    $mnemonic = Wallet::generateMnemonic();
    $result = Wallet::create($mnemonic, $password, []);
    jsonResponse(['success' => true, 'id' => $result['id'], 'mnemonic' => $result['mnemonic']]);
} catch (Throwable $e) {
    error_log('Setup error: ' . ($e->getCode() ? $e->getCode() : 'setup_failed'));
    jsonResponse(['error' => 'Failed to create wallet. Please try again.'], 500);
}
