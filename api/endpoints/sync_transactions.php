<?php

Api::requirePost();

require_once __DIR__ . '/_tx_helpers.php';

set_time_limit(300);

function syncTransactionsFetchTipHeight($coin) {
    return txTipHeightCached($coin);
}

function syncTransactionsFetchFeeFallback($coin, $txHash) {
    return txFeeFallback($coin, $txHash);
}

function syncTransactionsMapWithConcurrency($items, $limit, $fn, $deadline = null) {
    foreach ($items as $item) {
        if ($deadline !== null && time() >= $deadline) break;
        $fn($item);
    }
}

function syncTransactionsSyncWallet($walletId, $targetCoin, $deadline = null) {
    $decimals = txCoinDecimals($targetCoin);
    $tip = syncTransactionsFetchTipHeight($targetCoin);
    $addresses = Database::query(
        'SELECT address, address_type FROM wallet_addresses WHERE wallet_id = ? AND coin = ? ORDER BY address_index ASC',
        [$walletId, $targetCoin]
    );
    if (count($addresses) === 0) return;
    $seenHashes = [];
    syncTransactionsMapWithConcurrency($addresses, 5, function ($addrRow) use ($walletId, $tip, $targetCoin, &$seenHashes) {
        try {
            $blockchainTxs = BlockchainAPI::getTransactions($addrRow['address'], $targetCoin);
            if (!is_array($blockchainTxs)) return;
            foreach ($blockchainTxs as $tx) {
                if (!preg_match('/^[0-9a-fA-F]{16,128}$/', isset($tx['tx_hash']) ? $tx['tx_hash'] : '')) continue;
                if (isset($seenHashes[$tx['tx_hash']])) continue;
                $seenHashes[$tx['tx_hash']] = true;
                $storedAmount = (float) ($tx['value'] ?? 0);
                $fee = (float) ($tx['fee'] ?? 0);
                if ($fee === 0.0 && strlen($tx['tx_hash']) === 64) {
                    try {
                        $f = syncTransactionsFetchFeeFallback($targetCoin, $tx['tx_hash']);
                        if ($f !== null) $fee = $f;
                    } catch (Throwable $e) {}
                }
                $date = !empty($tx['timestamp']) ? gmdate('Y-m-d H:i:s', (int) $tx['timestamp']) : gmdate('Y-m-d H:i:s');
                $confirmations = $tx['confirmations'] ?? 0;
                $blockHeight = $tx['block_height'] ?? null;
                if ($tip && ($blockHeight || $confirmations > 0)) {
                    if ($blockHeight && $blockHeight < $tip) {
                        $confirmations = $tip - $blockHeight + 1;
                    } elseif ($confirmations > 0 && $confirmations <= $tip) {
                        $blockHeight = $tip - $confirmations + 1;
                    }
                }
                $fromAddr = $tx['from_address'] ?? (($storedAmount < 0) ? $addrRow['address'] : null);
                $toAddr = $tx['to_address'] ?? (($storedAmount < 0) ? null : $addrRow['address']);
                Database::execute(
                    'INSERT INTO transactions (wallet_id, coin, tx_hash, from_address, to_address, amount, fee, status, block_height, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE status = VALUES(status), block_height = VALUES(block_height), amount = VALUES(amount), fee = IF(VALUES(fee) > 0, VALUES(fee), fee), from_address = COALESCE(VALUES(from_address), from_address), to_address = COALESCE(VALUES(to_address), to_address)',
                    [$walletId, $targetCoin, $tx['tx_hash'], $fromAddr, $toAddr, $storedAmount, $fee, 'completed', $blockHeight, $date]
                );
            }
        } catch (Throwable $e) {
            error_log('Tx sync error for ' . $targetCoin . ' address ' . substr($addrRow['address'], 0, 12) . ': ' . $e->getMessage());
        }
    }, $deadline);
}

try {
    $token = Api::body('token');
    $coin = Api::body('coin');
    $activeCoins = Api::body('coins');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }

    $wallet = Wallet::getByToken($token);

    $coins = [];
    if ($coin) {
        $coins = [$coin];
    } elseif (is_array($activeCoins) && count($activeCoins) > 0) {
        $coins = $activeCoins;
    } else {
        $rows = Database::query('SELECT DISTINCT coin FROM wallet_addresses WHERE wallet_id = ?', [$wallet['id']]);
        foreach ($rows as $r) {
            $coins[] = $r['coin'];
        }
    }

    $force = Api::body('force') ? true : false;

    // Network-pressure guard: skip coins already synced within the cooldown window.
    // Cooldown is recorded on every call; `force` bypasses the check (e.g. manual refresh).
    $pendingCoins = [];
    foreach ($coins as $c) {
        $fresh = syncCooldownActive($wallet['id'], 'transactions', $c, 60);
        if ($force || !$fresh) {
            $pendingCoins[] = $c;
        }
    }
    $coins = $pendingCoins;
    if (count($coins) === 0) {
        jsonResponse(['success' => true, 'synced' => 0, 'coins' => 0, 'skipped' => true]);
    }

    $synced = 0;
    $syncDeadline = time() + 180;
    foreach ($coins as $c) {
        if (time() >= $syncDeadline) break;
        try {
            $addrs = Database::query(
                'SELECT COUNT(*) AS cnt FROM wallet_addresses WHERE wallet_id = ? AND coin = ?',
                [$wallet['id'], $c]
            );
            if ((int)($addrs[0]['cnt'] ?? 0) === 0) continue;
            syncTransactionsSyncWallet($wallet['id'], $c, $syncDeadline);
            $synced++;
        } catch (Throwable $e) {
            error_log('Sync tx error for ' . $c . ': ' . $e->getMessage());
        }
    }

    jsonResponse(['success' => true, 'synced' => $synced, 'coins' => count($coins), 'timedOut' => time() >= $syncDeadline]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Session expired'], 401);
    }
    jsonResponse(['error' => 'Internal server error'], 500);
}
