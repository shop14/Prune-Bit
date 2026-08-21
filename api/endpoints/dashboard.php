<?php

Api::requirePost();

require_once __DIR__ . '/_tx_helpers.php';

try {
    $token = Api::body('token');
    $coins = Api::body('coins');
    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }
    $wallet = Wallet::getByToken($token);

    $coinFilter = '';
    $params = [$wallet['id']];
    if (is_array($coins) && count($coins) > 0) {
        $placeholders = array_fill(0, count($coins), '?');
        $coinFilter = ' AND coin IN (' . implode(',', $placeholders) . ')';
        foreach ($coins as $c) {
            $params[] = $c;
        }
    }

    $addressRows = Database::query(
        'SELECT coin, address_type, address, address_index, balance, unconfirmed_balance, last_synced FROM wallet_addresses WHERE wallet_id = ?' . $coinFilter . ' ORDER BY coin, FIELD(address_type, "P2WPKH", "BECH32", "P2SH", "P2PKH"), address_index',
        $params
    );

    $addresses = [];
    foreach ($addressRows as $a) {
        $bal = (float) ($a['balance'] ?? 0);
        $addresses[] = [
            'coin' => $a['coin'],
            'type' => $a['address_type'] === 'BECH32' ? 'P2WPKH' : $a['address_type'],
            'address' => $a['address'],
            'index' => $a['address_index'],
            'balance' => $bal,
            'unconfirmed' => (float) ($a['unconfirmed_balance'] ?? 0),
            'last_synced' => $a['last_synced']
        ];
    }

    $coinTotals = [];
    foreach ($addressRows as $a) {
        $coin = $a['coin'];
        if (!isset($coinTotals[$coin])) {
            $coinTotals[$coin] = 0;
        }
        $coinTotals[$coin] += (float) ($a['balance'] ?? 0);
    }

    $recentTransactions = Database::query(
        "SELECT coin, tx_hash, from_address, to_address, amount, fee, confirmations, status, block_height, created_at, (CASE WHEN amount >= 0 THEN 'received' ELSE 'sent' END) as type FROM transactions WHERE wallet_id = ?" . $coinFilter . " ORDER BY created_at DESC LIMIT 50",
        $params
    );

    $tips = [];
    $uniqueCoins = array_unique(array_filter(array_column($recentTransactions, 'coin')));
    foreach ($uniqueCoins as $c) {
        $tips[$c] = txTipHeightCached($c);
    }

    $enrichedTxs = [];
    foreach ($recentTransactions as $tx) {
        $tip = isset($tips[$tx['coin']]) ? $tips[$tx['coin']] : null;
        $confirmations = (int) ($tx['confirmations'] ?? 0);
        if ($tx['status'] === 'completed' && $tx['block_height'] && $tip) {
            $confirmations = max(0, $tip - (int) $tx['block_height'] + 1);
        }
        $dateStr = $tx['created_at'] ? date('n/j/Y, g:i:s A', strtotime($tx['created_at'])) : '';
        $enrichedTxs[] = [
            'coin' => $tx['coin'],
            'tx_hash' => $tx['tx_hash'],
            'from_address' => $tx['from_address'],
            'to_address' => $tx['to_address'],
            'amount' => abs((float) ($tx['amount'] ?? 0)),
            'fee' => $tx['fee'] ?? 0,
            'confirmations' => $confirmations,
            'type' => $tx['type'],
            'date' => $dateStr,
            'status' => $tx['status'] === 'completed' ? 'confirmed' : ($tx['status'] ? $tx['status'] : 'pending')
        ];
    }

    $totalCount = Database::query(
        'SELECT COUNT(*) as cnt, SUM(CASE WHEN amount >= 0 THEN 1 ELSE 0 END) as received_cnt, SUM(CASE WHEN amount < 0 THEN 1 ELSE 0 END) as sent_cnt, SUM(amount) as net, MAX(created_at) as last_tx FROM transactions WHERE wallet_id = ?' . $coinFilter,
        $params
    );
    $tc = count($totalCount) > 0 ? $totalCount[0] : [];

    jsonResponse([
        'success' => true,
        'walletId' => $wallet['id'],
        'hasMnemonic' => strpos($wallet['id'], 'wallet_pk_') !== 0,
        'addresses' => $addresses,
        'balances' => (object) $coinTotals,
        'recentTransactions' => $enrichedTxs,
        'totalTx' => (int) ($tc['cnt'] ?? 0),
        'sentTx' => (int) ($tc['sent_cnt'] ?? 0),
        'receivedTx' => (int) ($tc['received_cnt'] ?? 0),
        'lastTx' => $tc && $tc['last_tx'] ? $tc['last_tx'] : null
    ]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Session expired'], 401);
    }
    error_log('Dashboard API error: ' . ($e->getCode() ? $e->getCode() : 'dashboard_failed'));
    jsonResponse(['error' => 'Internal server error'], 500);
}
