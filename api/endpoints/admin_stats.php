<?php

Api::requirePost();
requireAdminToken();

try {
    $totalWalletsRows = Database::query('SELECT COUNT(*) as count FROM wallets');
    $totalBalanceRows = Database::query('SELECT SUM(total_balance) as sum FROM wallets');
    $recentActivity = Database::query('SELECT id, last_access, total_balance, id_coin, created_at FROM wallets ORDER BY last_access DESC LIMIT 10');
    $allWallets = Database::query('SELECT id, last_access, total_balance, id_coin, created_at FROM wallets');

    jsonResponse([
        'success' => true,
        'stats' => [
            'totalWallets' => $totalWalletsRows[0]['count'],
            'totalBalance' => $totalBalanceRows[0]['sum'] ?? 0,
            'recentActivity' => $recentActivity,
            'allWallets' => $allWallets,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
