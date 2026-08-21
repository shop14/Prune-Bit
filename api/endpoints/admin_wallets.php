<?php

Api::requirePost();
requireAdminToken();

try {
    $wallets = Database::query('SELECT id, id_coin, total_balance, created_at, last_access, profile FROM wallets');
    jsonResponse(['success' => true, 'wallets' => $wallets]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
