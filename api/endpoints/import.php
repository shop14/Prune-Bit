<?php

Api::requirePost();

try {
    $captchaToken = Api::body('captcha_token');
    $captchaCode = Api::body('captcha_code');
    $captchaResult = Captcha::verify($captchaToken, $captchaCode);
    if (!$captchaResult['valid']) {
        jsonResponse(['error' => $captchaResult['error']], 400);
    }

    $mnemonic = Api::body('mnemonic');
    $password = Api::body('password');

    if (!$mnemonic || !$password) {
        jsonResponse(['error' => 'Mnemonic and PIN are required. Please enter both fields.'], 400);
    }

    $trimmed = trim($mnemonic);
    $words = preg_split('/\s+/', $trimmed);
    if (!in_array(count($words), [12, 15, 18, 21, 24])) {
        jsonResponse(['error' => 'Invalid mnemonic format. Must be 12, 15, 18, 21, or 24 words.'], 400);
    }

    if (!Bip39::validateMnemonic($trimmed)) {
        jsonResponse(['error' => 'Invalid seed phrase. Please check your words.'], 400);
    }

    $walletId = Encryption::hashMnemonic($trimmed);

    $existingWallets = Database::query('SELECT id, password_hash FROM wallets WHERE id = ?', [$walletId]);

    if (count($existingWallets) > 0) {
        $wallet = $existingWallets[0];
        $passwordMatch = Wallet::verifyPassword($password, $wallet['password_hash']);

        if (!$passwordMatch) {
            jsonResponse(['error' => 'Invalid PIN for this wallet. The seed phrase exists but the PIN does not match.'], 400);
        }

        $token = Session::create($walletId, 24, Session::metaFromRequest());
        Wallet::repairEthAddresses($walletId, $trimmed, $password);
        Wallet::repairBchAddresses($walletId, $trimmed, $password);
        jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => true]);
    }

    $result = Wallet::import($trimmed, $password, [], false);
    $token = Session::create($result['id'], 24, Session::metaFromRequest());
    jsonResponse(['success' => true, 'token' => $token, 'walletId' => $result['id'], 'alreadyExists' => false]);
} catch (Throwable $e) {
    error_log('Import error: ' . ($e->getCode() ? $e->getCode() : 'import_failed'));
    jsonResponse(['error' => 'Something went wrong during import. Please try again.'], 500);
}
