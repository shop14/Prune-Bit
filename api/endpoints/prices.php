<?php
try {
    $now = microtime(true);
    $nowDb = null;

    // In-process fast path (covers repeated calls within one request).
    if (isset($GLOBALS['prices_cache']) && is_array($GLOBALS['prices_cache']) && (($GLOBALS['prices_cache']['_ts'] ?? 0) > 0) && ($now - $GLOBALS['prices_cache']['_ts']) <= 60) {
        $cached = $GLOBALS['prices_cache'];
    } else {
        $cached = null;
        // DB-backed cache shared across requests (avoids hammering CoinGecko on every poll).
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS prices_cache (
                    coin VARCHAR(16) NOT NULL PRIMARY KEY,
                    usd DECIMAL(24,12) NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $rows = Database::query('SELECT coin, usd, UNIX_TIMESTAMP(updated_at) AS ts FROM prices_cache');
            $dbFresh = count($rows) > 0;
            $dbCached = ['_ts' => $now];
            foreach ($rows as $r) {
                if ($nowDb === null) $nowDb = (int) Database::query('SELECT UNIX_TIMESTAMP() AS ts')[0]['ts'];
                if ($nowDb - (int) $r['ts'] > 60) {
                    $dbFresh = false;
                    break;
                }
                $dbCached[$r['coin']] = (float) $r['usd'];
            }
            if ($dbFresh) $cached = $dbCached;
        } catch (Throwable $e) {}
    }

    if ($cached === null) {
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,litecoin,dogecoin,bitcoin-cash,dash,digibyte,ravencoin,bitcoin-gold,zcash,bitcoin-sv,verge,qtum,vertcoin,komodo,ethereum-classic,tether,matic-network,binancecoin,kaspa,ripple&vs_currencies=usd';
        $resp = httpRequest($url, 'GET');
        if ($resp !== null && $resp['status'] === 200 && is_array($resp['json'])) {
            $data = $resp['json'];
            $coinToGecko = [
                'BTC' => 'bitcoin', 'ETH' => 'ethereum', 'LTC' => 'litecoin', 'DOGE' => 'dogecoin',
                'BCH' => 'bitcoin-cash', 'DASH' => 'dash', 'DGB' => 'digibyte', 'RVN' => 'ravencoin',
                'BTG' => 'bitcoin-gold', 'ZEC' => 'zcash', 'BSV' => 'bitcoin-sv', 'XVG' => 'verge',
                'QTUM' => 'qtum', 'VTC' => 'vertcoin', 'KMD' => 'komodo', 'ETC' => 'ethereum-classic',
                'USDT' => 'tether', 'POLYGON' => 'matic-network', 'BSC' => 'binancecoin', 'KASPA' => 'kaspa', 'XRP' => 'ripple',
            ];
            $cached = ['_ts' => $now];
            foreach ($coinToGecko as $coin => $gid) {
                if (isset($data[$gid]) && isset($data[$gid]['usd']) && $data[$gid]['usd'] !== null) {
                    $cached[$coin] = (float) $data[$gid]['usd'];
                }
            }
            try {
                Database::execute('DELETE FROM prices_cache');
                foreach ($cached as $k => $v) {
                    if ($k === '_ts') continue;
                    Database::execute('INSERT INTO prices_cache (coin, usd, updated_at) VALUES (?, ?, NOW())', [$k, $v]);
                }
            } catch (Throwable $e) {}
        } else {
            $cached = ['_ts' => $now];
        }
    }
    $GLOBALS['prices_cache'] = $cached;

    $prices = [];
    foreach ($cached as $k => $v) {
        if ($k !== '_ts') $prices[$k] = $v;
    }
    $coins = Api::body('coins');
    if (is_array($coins) && count($coins) > 0) {
        $filtered = [];
        foreach ($coins as $c) {
            if (isset($prices[$c])) $filtered[$c] = $prices[$c];
        }
        jsonResponse(['success' => true, 'prices' => $filtered]);
    }
    jsonResponse(['success' => true, 'prices' => $prices]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'prices' => []]);
}
