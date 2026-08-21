<?php

Api::requirePost();
requireAdminToken();

try {
    Database::execute('DELETE FROM sessions WHERE expires_at < NOW()');
    Database::execute('DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)');

    $sessions = Database::query(
        "SELECT CONCAT(LEFT(token, 8), '...', RIGHT(token, 4)) AS token, wallet_id, expires_at, last_activity FROM sessions WHERE expires_at > NOW() AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );

    $walletIds = [];
    foreach ($sessions as $s) {
        if (!empty($s['wallet_id']) && !in_array($s['wallet_id'], $walletIds)) {
            $walletIds[] = $s['wallet_id'];
        }
    }

    $wallets = [];
    if (count($walletIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($walletIds), '?'));
        $wallets = Database::query(
            'SELECT id, id_coin, total_balance, created_at FROM wallets WHERE id IN (' . $placeholders . ')',
            $walletIds
        );
    }

    $walletMap = [];
    foreach ($wallets as $w) {
        $walletMap[$w['id']] = $w;
    }

    $enriched = [];
    foreach ($sessions as $s) {
        $w = isset($walletMap[$s['wallet_id']]) ? $walletMap[$s['wallet_id']] : [];
        $enriched[] = array_merge($s, [
            'coin' => isset($w['id_coin']) ? $w['id_coin'] : '-',
            'balance' => isset($w['total_balance']) ? $w['total_balance'] : 0,
            'wallet_created' => isset($w['created_at']) ? $w['created_at'] : null,
        ]);
    }

    jsonResponse(['success' => true, 'sessions' => $enriched]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
