<?php

Api::requirePost();

require_once __DIR__ . '/_tx_helpers.php';

function getTransactionsFetchTipHeight($coin) {
    return txTipHeightCached($coin);
}

function getTransactionsFetchFeeFallback($coin, $txHash) {
    return txFeeFallback($coin, $txHash);
}

function getTransactionsToLocaleString($createdAt) {
    return $createdAt ? date('m/d/Y, h:i:s A', strtotime($createdAt)) : date('m/d/Y, h:i:s A');
}

try {
    $token = Api::body('token');
    $coin = Api::body('coin');
    $coins = Api::body('coins');
    $status = Api::body('status');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }

    $wallet = Wallet::getByToken($token);

    $limit = Api::body('limit', 100);
    $offset = Api::body('offset', 0);

    $sql = 'SELECT * FROM transactions WHERE wallet_id = ?';
    $params = [$wallet['id']];
    if ($coin) {
        $sql .= ' AND coin = ?';
        $params[] = $coin;
    } elseif (is_array($coins) && count($coins) > 0) {
        $sql .= ' AND coin IN (' . implode(',', array_fill(0, count($coins), '?')) . ')';
        foreach ($coins as $c) {
            $params[] = $c;
        }
    }
    if ($status && in_array($status, ['completed', 'pending', 'failed'], true)) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;
    $transactions = Database::query($sql, $params);

    $total = Database::query(
        'SELECT COUNT(*) AS cnt FROM transactions WHERE wallet_id = ?' .
        ($coin ? ' AND coin = ?' : (is_array($coins) && count($coins) > 0 ? ' AND coin IN (' . implode(',', array_fill(0, count($coins), '?')) . ')' : '')),
        array_merge([$wallet['id']], $coin ? [$coin] : (is_array($coins) && count($coins) > 0 ? $coins : []))
    );
    $totalCount = (int) ($total[0]['cnt'] ?? 0);

    $tips = [];
    $uniqueCoins = array_unique(array_filter(array_column($transactions, 'coin')));
    foreach ($uniqueCoins as $c) {
        $tips[$c] = getTransactionsFetchTipHeight($c);
    }

    $mapped = [];
    foreach ($transactions as $tx) {
        $tip = isset($tips[$tx['coin']]) ? $tips[$tx['coin']] : null;
        $confirmations = 0;
        if ($tx['status'] === 'completed' && $tx['block_height'] && $tip) {
            $confirmations = max(0, $tip - $tx['block_height'] + 1);
        }
        $type = (float) $tx['amount'] < 0 ? 'sent' : 'received';
        $row = $tx;
        $row['confirmations'] = $confirmations;
        $row['type'] = $type;
        $row['date'] = getTransactionsToLocaleString($tx['created_at']);
        $mapped[] = $row;
    }

    jsonResponse(['success' => true, 'transactions' => $mapped, 'total' => $totalCount, 'limit' => $limit, 'offset' => $offset]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Session expired'], 401);
    }
    jsonResponse(['error' => 'Internal server error'], 500);
}
