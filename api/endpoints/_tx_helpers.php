<?php
// Shared helpers for transaction status/confirmations.
// NOTE: endpoint files are `include`d inside Api::dispatch(), so top-level
// variables would land in function scope, not global scope. Using $GLOBALS
// explicitly guarantees the arrays are reachable from any helper function.

$GLOBALS['txCoinUnits'] = [
    'BTC' => 8, 'BTCT' => 8, 'LTC' => 8, 'DOGE' => 8, 'BCH' => 8, 'DASH' => 8, 'DGB' => 8, 'BTG' => 8, 'RVN' => 8,
    'ETH' => 18, 'ETC' => 18, 'USDT' => 6,
    'POLYGON' => 18, 'BSC' => 18, 'ZEC' => 8, 'BSV' => 8, 'XVG' => 8, 'QTUM' => 8, 'VTC' => 8, 'KMD' => 8, 'KASPA' => 8, 'XRP' => 6
];

$GLOBALS['txTipUrls'] = [
    'BTC' => 'https://blockstream.info/api/blocks/tip/height',
    'BTCT' => 'https://mempool.space/testnet/api/blocks/tip/height',
    'LTC' => 'https://litecoinspace.org/api/blocks/tip/height',
    'DOGE' => 'https://doge.blockscout.com/api/v1/blocks/tip/height',
    'BCH' => 'https://bch.blockscout.com/api/v1/blocks/tip/height',
    'DASH' => 'https://dash.blockscout.com/api/v1/blocks/tip/height',
    'DGB' => 'https://dgb.blockscout.com/api/v1/blocks/tip/height',
    'RVN' => 'https://ravencoin.network/api/getblockcount',
    'BTG' => 'https://btg.blockscout.com/api/v1/blocks/tip/height',
    'ETH' => 'https://eth.blockscout.com/api/v1/blocks/tip/height',
    'ETC' => 'https://etc.blockscout.com/api/v1/blocks/tip/height',
    'USDT' => 'https://eth.blockscout.com/api/v1/blocks/tip/height',
    'POLYGON' => 'https://polygon.blockscout.com/api/v1/blocks/tip/height',
    'BSC' => 'https://bsc.blockscout.com/api/v1/blocks/tip/height',
    'ZEC' => 'https://zcash.blockscout.com/api/v1/blocks/tip/height',
    'BSV' => 'https://api.whatsonchain.com/v1/bsv/main/block/height',
    'XVG' => 'https://verge-blockchain.info/api/getblockcount',
    'QTUM' => 'https://qtum.blockscout.com/api/v1/blocks/tip/height',
    'VTC' => 'https://explorer.vertcoin.org/api/getblockcount',
    'KMD' => 'https://kmdexplorer.io/api/getblockcount',
    'KASPA' => 'https://api.kaspa.org/info/header',
    'XRP' => 'https://xrplcluster.com'
];

$GLOBALS['txFeeFallbackUrls'] = [
    'BTC' => ['https://mempool.space/api/tx/', 'https://api.blockcypher.com/v1/btc/main/txs/'],
    'BTCT' => ['https://mempool.space/testnet/api/tx/'],
    'LTC' => ['https://litecoinspace.org/api/tx/', 'https://api.blockcypher.com/v1/ltc/main/txs/'],
    'DOGE' => ['https://doge.blockscout.com/api/v1/transactions/', 'https://api.blockcypher.com/v1/doge/main/txs/'],
    'DASH' => ['https://dash.blockscout.com/api/v1/transactions/', 'https://api.blockcypher.com/v1/dash/main/txs/'],
    'BCH' => ['https://bch.blockscout.com/api/v1/transactions/', 'https://api.blockcypher.com/v1/bch/main/txs/'],
    'ZEC' => ['https://zcash.blockscout.com/api/v1/transactions/'],
    'ETC' => ['https://etc.blockscout.com/api/v1/transactions/'],
    'QTUM' => ['https://qtum.blockscout.com/api/v1/transactions/'],
];

$GLOBALS['txTipCache'] = [];
$GLOBALS['txTipTableReady'] = false;
$GLOBALS['txSyncCooldownTableReady'] = false;

function txCoinDecimals($coin) {
    return $GLOBALS['txCoinUnits'][strtoupper($coin)] ?? 8;
}

// DB-backed tip height cache so the (otherwise cheap) dashboard endpoint does
// not call external block explorers on every poll. Falls back to txTipHeight().
function txTipHeightCached($coin, $ttlSeconds = 60) {
    $upper = strtoupper($coin);
    $cache = &$GLOBALS['txTipCache'];
    if (isset($cache[$upper]) && (time() - $cache[$upper]['ts']) < 30000) {
        return $cache[$upper]['height'];
    }
    try {
        if (!$GLOBALS['txTipTableReady']) {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS tx_tip_cache (
                    coin VARCHAR(16) NOT NULL PRIMARY KEY,
                    height INT NOT NULL,
                    fetched_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $GLOBALS['txTipTableReady'] = true;
        }
        $rows = Database::query('SELECT height, UNIX_TIMESTAMP(fetched_at) AS ts FROM tx_tip_cache WHERE coin = ?', [$upper]);
        if (count($rows) > 0) {
            $nowDb = (int) Database::query('SELECT UNIX_TIMESTAMP() AS ts')[0]['ts'];
            $age = $nowDb - (int) $rows[0]['ts'];
            if ($age >= 0 && $age < $ttlSeconds) {
                $cache[$upper] = ['height' => (int) $rows[0]['height'], 'ts' => time()];
                return (int) $rows[0]['height'];
            }
        }
    } catch (Throwable $e) {}
    $height = txTipHeight($upper);
    if ($height) {
        try {
            Database::execute(
                'INSERT INTO tx_tip_cache (coin, height, fetched_at) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE height = VALUES(height), fetched_at = NOW()',
                [$upper, $height]
            );
        } catch (Throwable $e) {}
        $cache[$upper] = ['height' => $height, 'ts' => time()];
    }
    return $height;
}

// Network-pressure guard: tracks the last time a wallet+kind+coin was synced and
// returns true when the cooldown window is still active so the caller can skip
// redundant external fetches. Fail-open (returns false) if the DB is unavailable.
function syncCooldownActive($walletId, $kind, $coin, $cooldownSeconds) {
    try {
        if (!$GLOBALS['txSyncCooldownTableReady']) {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS sync_cooldown (
                    sync_key VARCHAR(190) NOT NULL PRIMARY KEY,
                    last_run DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $GLOBALS['txSyncCooldownTableReady'] = true;
        }
        $key = substr($walletId . ':' . strtoupper($kind) . ':' . strtoupper($coin), 0, 190);
        $rows = Database::query('SELECT last_run, UNIX_TIMESTAMP(last_run) AS ts FROM sync_cooldown WHERE sync_key = ?', [$key]);
        if (count($rows) > 0) {
            $nowDb = (int) Database::query('SELECT UNIX_TIMESTAMP() AS ts')[0]['ts'];
            $age = $nowDb - (int) $rows[0]['ts'];
            if ($age >= 0 && $age < (int) $cooldownSeconds) {
                return true;
            }
        }
        Database::execute(
            'INSERT INTO sync_cooldown (sync_key, last_run) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE last_run = NOW()',
            [$key]
        );
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

function txTipHeight($coin) {
    $upper = strtoupper($coin);
    $cache = &$GLOBALS['txTipCache'];
    if (isset($cache[$upper]) && (time() - $cache[$upper]['ts']) < 30000) {
        return $cache[$upper]['height'];
    }
    $url = $GLOBALS['txTipUrls'][$upper] ?? null;
    if ($url === null) return null;
    try {
        if (in_array($upper, ['DOGE', 'BCH', 'DASH', 'DGB', 'RVN', 'BTG', 'ETH', 'ETC', 'USDT', 'POLYGON', 'BSC', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD', 'KASPA', 'XRP'])) {
            $resp = httpRequest($url, 'GET', null, [], 8);
            if ($resp === null) return null;
            if ($resp['status'] < 200 || $resp['status'] >= 300) return null;
            $data = json_decode($resp['body'], true);
            $height = is_array($data) ? ($data['height'] ?? null) : null;
            if ($height) $cache[$upper] = ['height' => $height, 'ts' => time()];
            return $height;
        }
        $resp = httpRequest($url, 'GET', null, [], 8);
        if ($resp === null) return null;
        if ($resp['status'] >= 200 && $resp['status'] < 300) {
            $body = trim($resp['body']);
            $data = json_decode($body, true);
            $height = is_array($data) ? ($data['height'] ?? null) : (int) $body;
            if ($height) $cache[$upper] = ['height' => $height, 'ts' => time()];
            return $height ? $height : null;
        }
    } catch (Throwable $e) {}
    return null;
}

function txFeeFallback($coin, $txHash) {
    $upper = strtoupper($coin);
    $apis = $GLOBALS['txFeeFallbackUrls'][$upper] ?? null;
    if ($apis === null) return null;
    foreach ($apis as $base) {
        try {
            $resp = httpRequest($base . $txHash, 'GET', null, [], 8);
            if ($resp === null) continue;
            if ($resp['status'] < 200 || $resp['status'] >= 300) continue;
            $data = json_decode($resp['body'], true);
            if (!is_array($data)) continue;
            if (isset($data['fee']) && is_numeric($data['fee'])) return (float) $data['fee'] / 1e8;
            if (isset($data['fees']) && is_numeric($data['fees'])) return (float) $data['fees'] / 1e8;
        } catch (Throwable $e) {}
    }
    return null;
}
