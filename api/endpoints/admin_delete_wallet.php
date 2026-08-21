<?php

Api::requirePost();
requireAdminToken();

try {
    $walletId = Api::body('wallet_id');
    if (!$walletId) {
        jsonResponse(['error' => 'Wallet ID required'], 400);
    }
    $exists = Database::query('SELECT id FROM wallets WHERE id = ?', [$walletId]);
    if (count($exists) === 0) {
        jsonResponse(['error' => 'Wallet not found'], 404);
    }
    Database::execute('DELETE FROM wallet_addresses WHERE wallet_id = ?', [$walletId]);
    Database::execute('DELETE FROM transactions WHERE wallet_id = ?', [$walletId]);
    Database::execute('DELETE FROM sessions WHERE wallet_id = ?', [$walletId]);
    Database::execute('DELETE FROM login_log WHERE wallet_id = ?', [$walletId]);
    Database::execute('DELETE FROM wallets WHERE id = ?', [$walletId]);
    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
