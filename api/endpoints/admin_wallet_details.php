<?php

Api::requirePost();
requireAdminToken();

try {
    $walletId = Api::body('wallet_id');

    if (!$walletId) {
        jsonResponse(['error' => 'Wallet ID required'], 400);
    }

    $wallets = Database::query(
        'SELECT id, id_coin, total_balance, created_at, last_access, profile FROM wallets WHERE id = ?',
        [$walletId]
    );

    if (count($wallets) === 0) {
        jsonResponse(['error' => 'Wallet not found'], 404);
    }

    $wallet = $wallets[0];

    $addresses = Database::query(
        'SELECT id, wallet_id, address, coin, balance, unconfirmed_balance, address_index, address_type, created_at FROM wallet_addresses WHERE wallet_id = ? LIMIT 10',
        [$walletId]
    );

    $transactions = Database::query(
        'SELECT * FROM transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 10',
        [$walletId]
    );

    jsonResponse([
        'success' => true,
        'wallet' => [
            'id' => $wallet['id'],
            'id_coin' => $wallet['id_coin'],
            'total_balance' => $wallet['total_balance'],
            'created_at' => $wallet['created_at'],
            'last_access' => $wallet['last_access'],
            'profile' => $wallet['profile'],
            'has_seed' => false,
        ],
        'addresses' => $addresses,
        'transactions' => $transactions,
    ]);
} catch (Throwable $e) {
    error_log('Wallet details error: ' . ($e->getCode() ? $e->getCode() : 'unknown'));
    jsonResponse(['error' => 'Internal server error'], 500);
}
