<?php

Api::requirePost();

require_once __DIR__ . '/_tx_helpers.php';

set_time_limit(300);

function syncBalancesMapWithConcurrency($items, $limit, $fn, $deadline = null) {
    foreach ($items as $item) {
        if ($deadline !== null && time() >= $deadline) break;
        $fn($item);
    }
}

try {
    $token = Api::body('token');
    $coin = Api::body('coin');
    $coins = Api::body('coins');

    $syncDeadline = time() + 100;
    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }

    $wallet = Wallet::getByToken($token);

    $force = Api::body('force') ? true : false;

    $requestedCoins = [];
    if ($coin) {
        $requestedCoins[] = $coin;
    } elseif (is_array($coins) && count($coins) > 0) {
        $requestedCoins = array_values($coins);
    } else {
        $rowsAll = Database::query('SELECT DISTINCT coin FROM wallet_addresses WHERE wallet_id = ?', [$wallet['id']]);
        foreach ($rowsAll as $r) {
            $requestedCoins[] = $r['coin'];
        }
    }

    // Network-pressure guard: skip coins already synced within the cooldown window.
    // Cooldown is recorded on every call; `force` bypasses the check (e.g. manual refresh).
    $pendingCoins = [];
    foreach ($requestedCoins as $rc) {
        $fresh = syncCooldownActive($wallet['id'], 'balances', $rc, 45);
        if ($force || !$fresh) {
            $pendingCoins[] = $rc;
        }
    }
    if (count($pendingCoins) === 0) {
        jsonResponse(['success' => true, 'updated' => 0, 'total' => 0, 'skipped' => true]);
    }

    $sql = 'SELECT * FROM wallet_addresses WHERE wallet_id = ?';
    $params = [$wallet['id']];
    if (count($pendingCoins) === 1) {
        $sql .= ' AND coin = ?';
        $params[] = $pendingCoins[0];
    } else {
        $sql .= ' AND coin IN (' . implode(',', array_fill(0, count($pendingCoins), '?')) . ')';
        foreach ($pendingCoins as $pc) {
            $params[] = $pc;
        }
    }
    $addresses = Database::query($sql, $params);

    $updated = 0;
    syncBalancesMapWithConcurrency($addresses, 5, function ($addr) use (&$updated) {
        try {
            $data = BlockchainAPI::getBalance($addr['address'], $addr['coin']);
            Database::execute(
                'UPDATE wallet_addresses SET balance = ?, unconfirmed_balance = ?, last_synced = NOW() WHERE id = ?',
                [($data['balance'] ?? 0) ? $data['balance'] : 0, ($data['unconfirmed_balance'] ?? 0) ? $data['unconfirmed_balance'] : 0, $addr['id']]
            );
            $updated++;
        } catch (Throwable $e) {
            error_log('Sync error for ' . substr($addr['address'], 0, 20) . ': ' . substr($e->getMessage(), 0, 30));
        }
    }, $syncDeadline);

    jsonResponse(['success' => true, 'updated' => $updated, 'total' => count($addresses), 'timedOut' => time() >= $syncDeadline]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Session expired'], 401);
    }
    jsonResponse(['error' => 'Internal server error'], 500);
}
