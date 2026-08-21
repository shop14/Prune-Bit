<?php

Api::requirePost();
requireAdminToken();

try {
    Session::ensureLoginLogTable();

    $sessions = Database::query(
        "SELECT wallet_id, ip, country, user_agent, created_at AS last_activity FROM login_log ORDER BY created_at DESC, id DESC LIMIT 20"
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
            'SELECT id, id_coin, total_balance FROM wallets WHERE id IN (' . $placeholders . ')',
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
        ]);
    }

    jsonResponse(['success' => true, 'sessions' => $enriched]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
