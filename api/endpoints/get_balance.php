<?php

Api::requirePost();

try {
    $token = Api::body('token');
    $coin = Api::body('coin');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }

    // Non-custodial build: balances are read from stored addresses, so no PIN
    // is required and there is nothing to decrypt.
    $wallet = Wallet::getByToken($token);
    $balance = Wallet::getBalance($wallet['id'], $coin ? $coin : 'BTC');

    jsonResponse(['success' => true, 'balance' => $balance]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Invalid or expired session'], 401);
    }
    if (strpos($msg, 'Wallet not found') !== false) {
        jsonResponse(['error' => 'Wallet not found'], 404);
    }
    error_log('get_balance error: ' . $msg);
    jsonResponse(['error' => 'Internal server error'], 500);
}
