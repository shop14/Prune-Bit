<?php

Api::requirePost();

try {
    $token = Api::body('token');
    $coin = Api::body('coin');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 401);
    }

    $wallet = Wallet::getByToken($token);

    $sql = 'SELECT * FROM transactions WHERE wallet_id = ?';
    $params = [$wallet['id']];

    if ($coin) {
        $sql .= ' AND coin = ?';
        $params[] = $coin;
    }

    $sql .= ' ORDER BY created_at DESC';

    $transactions = Database::query($sql, $params);

    jsonResponse(['success' => true, 'transactions' => $transactions]);
} catch (Throwable $e) {
    if ($e->getMessage() === 'Invalid or expired session') {
        jsonResponse(['error' => 'Authentication failed'], 401);
    }
    jsonResponse(['error' => 'Internal server error'], 500);
}
