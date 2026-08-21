<?php

Api::requirePost();
requireAdminToken();

try {
    $transactions = Database::query('SELECT * FROM transactions ORDER BY created_at DESC LIMIT 100');
    jsonResponse(['success' => true, 'transactions' => $transactions]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
