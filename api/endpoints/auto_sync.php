<?php

Api::requirePost();

require_once __DIR__ . '/_tx_helpers.php';

set_time_limit(300);

function autoSyncFetchTipHeight($coin) {
    return txTipHeightCached($coin);
}

function autoSyncFetchFeeFallback($coin, $txHash) {
    return txFeeFallback($coin, $txHash);
}

function autoSyncBalances($walletId, $coins, $deadline) {
    $sql = 'SELECT * FROM wallet_addresses WHERE wallet_id = ?';
    $params = [$walletId];
    if (count($coins) === 1) {
        $sql .= ' AND coin = ?';
        $params[] = $coins[0];
    } else {
        $sql .= ' AND coin IN (' . implode(',', array_fill(0, count($coins), '?')) . ')';
        foreach ($coins as $c) { $params[] = $c; }
    }
    $addresses = Database::query($sql, $params);

    $updated = 0;
    foreach ($addresses as $addr) {
        if (time() >= $deadline) break;
        try {
            $data = BlockchainAPI::getBalance($addr['address'], $addr['coin']);
            Database::execute(
                'UPDATE wallet_addresses SET balance = ?, unconfirmed_balance = ?, last_synced = NOW() WHERE id = ?',
                [($data['balance'] ?? 0) ? $data['balance'] : 0, ($data['unconfirmed_balance'] ?? 0) ? $data['unconfirmed_balance'] : 0, $addr['id']]
            );
            $updated++;
        } catch (Throwable $e) {
            error_log('Auto sync balance error for ' . substr($addr['address'], 0, 20) . ': ' . substr($e->getMessage(), 0, 30));
        }
    }
    return $updated;
}

function autoSyncTransactions($walletId, $coins, $deadline) {
    $synced = 0;
    foreach ($coins as $c) {
        if (time() >= $deadline) break;
        $addrs = Database::query(
            'SELECT COUNT(*) AS cnt FROM wallet_addresses WHERE wallet_id = ? AND coin = ?',
            [$walletId, $c]
        );
        if ((int)($addrs[0]['cnt'] ?? 0) === 0) continue;

        $decimals = txCoinDecimals($c);
        $tip = autoSyncFetchTipHeight($c);
        $addresses = Database::query(
            'SELECT address, address_type FROM wallet_addresses WHERE wallet_id = ? AND coin = ? ORDER BY address_index ASC',
            [$walletId, $c]
        );
        $seenHashes = [];
        foreach ($addresses as $addrRow) {
            if (time() >= $deadline) break;
            try {
                $blockchainTxs = BlockchainAPI::getTransactions($addrRow['address'], $c);
                if (!is_array($blockchainTxs)) continue;
                foreach ($blockchainTxs as $tx) {
                    if (!preg_match('/^[0-9a-fA-F]{16,128}$/', $tx['tx_hash'] ?? '')) continue;
                    if (isset($seenHashes[$tx['tx_hash']])) continue;
                    $seenHashes[$tx['tx_hash']] = true;
                    $storedAmount = (float) ($tx['value'] ?? 0);
                    $fee = (float) ($tx['fee'] ?? 0);
                    if ($fee === 0.0 && strlen($tx['tx_hash']) === 64) {
                        try { $f = autoSyncFetchFeeFallback($c, $tx['tx_hash']); if ($f !== null) $fee = $f; } catch (Throwable $e) {}
                    }
                    $date = !empty($tx['timestamp']) ? gmdate('Y-m-d H:i:s', (int) $tx['timestamp']) : gmdate('Y-m-d H:i:s');
                    $confirmations = $tx['confirmations'] ?? 0;
                    $blockHeight = $tx['block_height'] ?? null;
                    if ($tip && ($blockHeight || $confirmations > 0)) {
                        if ($blockHeight && $blockHeight < $tip) { $confirmations = $tip - $blockHeight + 1; }
                        elseif ($confirmations > 0 && $confirmations <= $tip) { $blockHeight = $tip - $confirmations + 1; }
                    }
                    $fromAddr = $tx['from_address'] ?? (($storedAmount < 0) ? $addrRow['address'] : null);
                    $toAddr = $tx['to_address'] ?? (($storedAmount < 0) ? null : $addrRow['address']);
                    Database::execute(
                        'INSERT INTO transactions (wallet_id, coin, tx_hash, from_address, to_address, amount, fee, status, block_height, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE status = VALUES(status), block_height = VALUES(block_height), amount = VALUES(amount), fee = IF(VALUES(fee) > 0, VALUES(fee), fee), from_address = COALESCE(VALUES(from_address), from_address), to_address = COALESCE(VALUES(to_address), to_address)',
                        [$walletId, $c, $tx['tx_hash'], $fromAddr, $toAddr, $storedAmount, $fee, 'completed', $blockHeight, $date]
                    );
                }
                $synced++;
            } catch (Throwable $e) {
                error_log('Auto sync tx error for ' . $c . ' address ' . substr($addrRow['address'], 0, 12) . ': ' . $e->getMessage());
            }
        }
    }
    return $synced;
}

try {
    $token = Api::body('token');
    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }
    $wallet = Wallet::getByToken($token);

    $coins = Api::body('coins');
    if (!is_array($coins) || count($coins) === 0) {
        $rows = Database::query('SELECT DISTINCT coin FROM wallet_addresses WHERE wallet_id = ?', [$wallet['id']]);
        foreach ($rows as $r) { $coins[] = $r['coin']; }
    }

    $force = Api::body('force') ? true : false;
    $syncDeadline = time() + 180;

    $balanceUpdated = autoSyncBalances($wallet['id'], $coins, $syncDeadline);
    $txSynced = autoSyncTransactions($wallet['id'], $coins, $syncDeadline);

    jsonResponse(['success' => true, 'balance_updated' => $balanceUpdated, 'tx_synced' => $txSynced, 'coins' => count($coins), 'timedOut' => time() >= $syncDeadline]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Session expired'], 401);
    }
    jsonResponse(['error' => 'Internal server error'], 500);
}