<?php

Api::requirePost();

try {
    $token = Api::body('token');
    $coin = Api::body('coin');
    $password = Api::body('password');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }

    if (!$password) {
        jsonResponse(['error' => 'PIN required'], 400);
    }

    $wallet = Wallet::getByToken($token);
    $resolvedCoin = $coin ? $coin : 'BTC';
    $address = Wallet::deriveAddress($wallet, $resolvedCoin, $password);

    jsonResponse(['success' => true, 'address' => $address, 'coin' => $resolvedCoin]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Authentication failed'], 500);
}
