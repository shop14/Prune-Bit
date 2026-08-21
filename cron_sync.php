<?php

require_once __DIR__ . '/autoload.php';

set_time_limit(300);

require_once __DIR__ . '/api/endpoints/_tx_helpers.php';

function cronSyncFetchTipHeight($coin) {
    return txTipHeightCached($coin);
}

function cronSyncFetchFeeFallback($coin, $txHash) {
    return txFeeFallback($coin, $txHash);
}

function cronSyncBalances($walletId, $coins, $deadline) {
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
            error_log('Cron sync balance error for ' . substr($addr['address'], 0, 20) . ': ' . substr($e->getMessage(), 0, 30));
        }
    }
    return $updated;
}

function cronSyncTransactions($walletId, $coins, $deadline) {
    $synced = 0;
    foreach ($coins as $c) {
        if (time() >= $deadline) break;
        $addrs = Database::query(
            'SELECT COUNT(*) AS cnt FROM wallet_addresses WHERE wallet_id = ? AND coin = ?',
            [$walletId, $c]
        );
        if ((int)($addrs[0]['cnt'] ?? 0) === 0) continue;

        $decimals = txCoinDecimals($c);
        $tip = cronSyncFetchTipHeight($c);
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
                        try { $f = cronSyncFetchFeeFallback($c, $tx['tx_hash']); if ($f !== null) $fee = $f; } catch (Throwable $e) {}
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
                error_log('Cron sync tx error for ' . $c . ' address ' . substr($addrRow['address'], 0, 12) . ': ' . $e->getMessage());
            }
        }
    }
    return $synced;
}

echo "=== CRON AUTO SYNC STARTED ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$deadline = time() + 300;
$activeSessions = Database::query('SELECT DISTINCT wallet_id FROM sessions WHERE expires_at > NOW() AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
echo "Found " . count($activeSessions) . " active wallet(s)\n";

$totalBalances = 0;
$totalTxs = 0;
$walletsProcessed = 0;

foreach ($activeSessions as $session) {
    $walletId = $session['wallet_id'];
    echo "Processing wallet: $walletId\n";

    $wallet = Database::query('SELECT * FROM wallets WHERE id = ?', [$walletId]);
    if (count($wallet) === 0) {
        echo "  Wallet not found, skipping\n";
        continue;
    }

    $rows = Database::query('SELECT DISTINCT coin FROM wallet_addresses WHERE wallet_id = ?', [$walletId]);
    $coins = [];
    foreach ($rows as $r) { $coins[] = $r['coin']; }
    if (count($coins) === 0) {
        echo "  No coins found, skipping\n";
        continue;
    }

    try {
        $balanceUpdated = cronSyncBalances($walletId, $coins, $deadline);
        $txSynced = cronSyncTransactions($walletId, $coins, $deadline);
        $totalBalances += $balanceUpdated;
        $totalTxs += $txSynced;
        $walletsProcessed++;
        echo "  Balances updated: $balanceUpdated, Transactions synced: $txSynced\n";
    } catch (Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== CRON AUTO SYNC COMPLETED ===\n";
echo "Wallets processed: $walletsProcessed\n";
echo "Total balances updated: $totalBalances\n";
echo "Total transactions synced: $totalTxs\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";