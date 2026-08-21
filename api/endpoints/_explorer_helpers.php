<?php

if (!function_exists('expl_coins')) {
    function expl_coins() {
        $t = [
            'BTC' => ['name' => 'Bitcoin', 'logo' => 'btc', 'tx' => 'https://mempool.space/tx/'],
            'BTCT' => ['name' => 'Bitcoin Testnet', 'logo' => 'btc', 'tx' => 'https://mempool.space/testnet/tx/'],
            'ETH' => ['name' => 'Ethereum', 'logo' => 'eth', 'tx' => 'https://etherscan.io/tx/'],
            'LTC' => ['name' => 'Litecoin', 'logo' => 'ltc', 'tx' => 'https://litecoinspace.org/tx/'],
            'DOGE' => ['name' => 'Dogecoin', 'logo' => 'doge', 'tx' => 'https://dogechain.info/transaction/'],
            'BCH' => ['name' => 'Bitcoin Cash', 'logo' => 'bch', 'tx' => 'https://blockchair.com/bitcoin-cash/transaction/'],
            'DASH' => ['name' => 'Dash', 'logo' => 'dash', 'tx' => 'https://blockchair.com/dash/transaction/'],
            'DGB' => ['name' => 'DigiByte', 'logo' => 'dgb', 'tx' => 'https://blockchair.com/digibyte/transaction/'],
            'RVN' => ['name' => 'Ravencoin', 'logo' => 'rvn', 'tx' => 'https://blockbook.ravencoin.org/tx/'],
            'BTG' => ['name' => 'Bitcoin Gold', 'logo' => 'btg', 'tx' => 'https://btgexplorer.com/tx/'],
            'ZEC' => ['name' => 'Zcash', 'logo' => 'zec', 'tx' => 'https://zecblock.co/tx/'],
            'BSV' => ['name' => 'Bitcoin SV', 'logo' => 'bsv', 'tx' => 'https://whatsonchain.com/tx/'],
            'XVG' => ['name' => 'Verge', 'logo' => 'xvg', 'tx' => 'https://verge-blockchain.info/tx/'],
            'QTUM' => ['name' => 'Qtum', 'logo' => 'qtum', 'tx' => 'https://explorer.qtum.org/tx/'],
            'VTC' => ['name' => 'Vertcoin', 'logo' => 'vtc', 'tx' => 'https://explorer.vertcoin.org/tx/'],
            'KMD' => ['name' => 'Komodo', 'logo' => 'kmd', 'tx' => 'https://kmdexplorer.io/tx/'],
            'ETC' => ['name' => 'Ethereum Classic', 'logo' => 'etc', 'tx' => 'https://etc.blockscout.com/tx/'],
            'USDT' => ['name' => 'Tether USDT (ERC20)', 'logo' => 'usdt', 'tx' => 'https://etherscan.io/tx/'],
            'POLYGON' => ['name' => 'Polygon', 'logo' => 'matic', 'tx' => 'https://polygonscan.com/tx/'],
            'BSC' => ['name' => 'BNB Chain', 'logo' => 'bnb', 'tx' => 'https://bscscan.com/tx/'],
            'KASPA' => ['name' => 'Kaspa', 'logo' => 'kaspa', 'tx' => 'https://explorer.kaspa.org/tx/'],
            'XRP' => ['name' => 'Ripple', 'logo' => 'xrp', 'tx' => 'https://livenet.xrpl.org/transactions/'],
        ];
        foreach ($t as $k => $v) unset($t[$k]['tx']);
        return $t;
    }
}

if (!function_exists('expl_txUrl')) {
    function expl_txUrl($coin, $hash) {
        $cu = strtoupper((string) $coin);
        $base = [
            'BTC' => 'https://mempool.space/tx/', 'BTCT' => 'https://mempool.space/testnet/tx/',
            'ETH' => 'https://etherscan.io/tx/', 'LTC' => 'https://litecoinspace.org/tx/',
            'DOGE' => 'https://dogechain.info/transaction/', 'BCH' => 'https://blockchair.com/bitcoin-cash/transaction/',
            'DASH' => 'https://blockchair.com/dash/transaction/', 'DGB' => 'https://blockchair.com/digibyte/transaction/',
            'RVN' => 'https://blockbook.ravencoin.org/tx/', 'BTG' => 'https://btgexplorer.com/tx/',
            'ZEC' => 'https://zecblock.co/tx/', 'BSV' => 'https://whatsonchain.com/tx/',
            'XVG' => 'https://verge-blockchain.info/tx/', 'QTUM' => 'https://explorer.qtum.org/tx/',
            'VTC' => 'https://explorer.vertcoin.org/tx/', 'KMD' => 'https://kmdexplorer.io/tx/',
            'ETC' => 'https://etc.blockscout.com/tx/', 'USDT' => 'https://etherscan.io/tx/',
            'POLYGON' => 'https://polygonscan.com/tx/', 'BSC' => 'https://bscscan.com/tx/',
            'KASPA' => 'https://explorer.kaspa.org/tx/', 'XRP' => 'https://livenet.xrpl.org/transactions/',
        ][$cu] ?? 'https://mempool.space/tx/';
        return $base . $hash;
    }
}

if (!function_exists('expl_apiUrl')) {
    function expl_apiUrl($coin) {
        $c = strtoupper((string) $coin);
        $map = [
            'BTC' => 'https://mempool.space/api/address/', 'BTCT' => 'https://mempool.space/testnet/api/address/',
            'ETH' => 'https://api.etherscan.io/api?module=account&action=txlist&address=',
            'LTC' => 'https://litecoinspace.org/api/address/',
            'DOGE' => 'https://api.blockcypher.com/v1/doge/main/addrs/',
            'BCH' => 'https://api.blockcypher.com/v1/bch/main/addrs/', 'DASH' => 'https://api.blockcypher.com/v1/dash/main/addrs/',
            'DGB' => 'https://api.blockcypher.com/v1/dgb/main/addrs/', 'BTG' => 'https://api.blockcypher.com/v1/btg/main/addrs/',
            'ZEC' => 'https://api.blockcypher.com/v1/zec/main/addrs/', 'BSV' => 'https://api.blockcypher.com/v1/bsv/main/addrs/',
            'XVG' => 'https://api.blockcypher.com/v1/xvg/main/addrs/', 'QTUM' => 'https://api.blockcypher.com/v1/qtum/main/addrs/',
            'VTC' => 'https://api.blockcypher.com/v1/vtc/main/addrs/', 'KMD' => 'https://api.blockcypher.com/v1/kmd/main/addrs/',
            'ETC' => 'https://etc.blockscout.com/api?module=account&action=txlist&address=',
            'USDT' => 'https://api.etherscan.io/api?module=account&action=tokentx&contractaddress=0xdAC17F958D2ee523a2206206994597C13D831ec7&address=',
            'POLYGON' => 'https://api.polygonscan.com/api?module=account&action=txlist&address=',
            'BSC' => 'https://api.bscscan.com/api?module=account&action=txlist&address=',
            'KASPA' => 'https://api.kaspa.org/addresses/',
        ];
        return $map[$c] ?? null;
    }
}

if (!function_exists('expl_rpcRippled')) {
    function expl_rpcRippled($method, $params) {
        $r = httpRequest('https://s2.ripple.com:51234', 'POST', json_encode(['method' => $method, 'params' => $params]), ['Content-Type: application/json']);
        if ($r === null) throw new Exception('rippled error');
        $result = $r['json']['result'] ?? null;
        if (!$result || ($result['status'] ?? null) === 'error') {
            throw new Exception($result ? ($result['error_message'] ?? ($result['error'] ?? 'rippled error')) : 'rippled error');
        }
        return $result;
    }
}

if (!function_exists('expl_fetchAddressTxsMempool')) {
    function expl_fetchAddressTxsMempool($address, $apiUrl) {
        $tipHeight = 0;
        try {
            $base = explode('/address/', $apiUrl)[0];
            $r = httpRequest($base . '/blocks/tip/height', 'GET');
            if ($r !== null && is_numeric(trim($r['body']))) $tipHeight = (int) trim($r['body']);
        } catch (Throwable $e) {}
        $txs = [];
        foreach (['/txs', '/txs/mempool'] as $suffix) {
            try {
                $r = httpRequest($apiUrl . rawurlencode($address) . $suffix, 'GET');
                if ($r !== null && $r['status'] >= 200 && $r['status'] < 300 && is_array($r['json'])) {
                    foreach ($r['json'] as $tx) {
                        if (is_array($tx) && isset($tx['txid'])) $txs[] = $tx;
                    }
                }
            } catch (Throwable $e) {}
        }
        $out = [];
        $seen = [];
        foreach ($txs as $tx) {
            $hash = $tx['txid'] ?? null;
            if ($hash !== null) {
                if (isset($seen[$hash])) continue;
                $seen[$hash] = true;
            }
            $received = 0.0; $sent = 0.0;
            foreach (($tx['vout'] ?? []) as $o) {
                if (($o['scriptpubkey_address'] ?? null) === $address) $received += (float) ($o['value'] ?? 0);
            }
            foreach (($tx['vin'] ?? []) as $inp) {
                $prev = $inp['prevout'] ?? [];
                if (($prev['scriptpubkey_address'] ?? null) === $address) $sent += (float) ($prev['value'] ?? 0);
            }
            $amount = $received - $sent;
            $bh = $tx['status']['block_height'] ?? null;
            $confirmed = !empty($tx['status']['confirmed']);
            $conf = ($confirmed && $bh) ? max(1, ($tipHeight ? $tipHeight - (int)$bh + 1 : 1)) : 0;
            $out[] = [
                'tx_hash' => $tx['txid'] ?? null, 'block_height' => $bh, 'confirmations' => $conf,
                'timestamp' => $tx['status']['block_time'] ?? null, 'amount' => $amount,
                'fee' => $tx['fee'] ?? 0, 'from_address' => $sent > 0 ? $address : null,
                'to_address' => $received > 0 ? $address : null, 'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsBlockcypher')) {
    function expl_fetchAddressTxsBlockcypher($address, $apiUrl) {
        $r = httpRequest($apiUrl . rawurlencode($address) . '/full?limit=100', 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $txs = $r['json']['txs'] ?? [];
        $out = [];
        foreach ($txs as $tx) {
            $received = 0; $sent = 0;
            foreach (($tx['outputs'] ?? []) as $o) {
                if (in_array($address, ($o['addresses'] ?? []))) $received += (int) ($o['value'] ?? 0);
            }
            foreach (($tx['inputs'] ?? []) as $inp) {
                if (in_array($address, ($inp['addresses'] ?? []))) $sent += (int) ($inp['output_value'] ?? 0);
            }
            $ts = strtotime($tx['confirmed'] ?? ($tx['received'] ?? ''));
            $out[] = [
                'tx_hash' => $tx['hash'] ?? null, 'block_height' => $tx['block_height'] ?? null,
                'confirmations' => $tx['confirmations'] ?? 0,
                'timestamp' => (is_numeric($ts) ? (int) $ts : null),
                'amount' => $received - $sent, 'fee' => $tx['fees'] ?? 0,
                'from_address' => $sent > 0 ? $address : null, 'to_address' => $received > 0 ? $address : null,
                'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsEtherscan')) {
    function expl_fetchAddressTxsEtherscan($address, $apiUrl) {
        $r = httpRequest($apiUrl . rawurlencode($address), 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $data = $r['json'];
        if (($data['status'] ?? '') !== '1' && ($data['message'] ?? '') !== 'No transactions found') return [];
        $txs = $data['result'] ?? [];
        $out = [];
        foreach ($txs as $tx) {
            $to = $tx['to'] ?? '';
            $amount = (strtolower($to) === strtolower($address) ? 1 : -1) * (float) ($tx['value'] ?? 0);
            $fee = ((float) ($tx['gasUsed'] ?? 0) * (float) ($tx['gasPrice'] ?? 0));
            $out[] = [
                'tx_hash' => $tx['hash'] ?? null, 'block_height' => (int) ($tx['blockNumber'] ?? 0),
                'confirmations' => (int) ($tx['confirmations'] ?? 0), 'timestamp' => (int) ($tx['timeStamp'] ?? 0),
                'amount' => $amount, 'fee' => $fee, 'from_address' => $tx['from'] ?? null,
                'to_address' => $tx['to'] ?? null, 'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsBlockscoutV2')) {
    function expl_fetchAddressTxsBlockscoutV2($address, $baseUrl) {
        $base = rtrim($baseUrl, '/api');
        $r = httpRequest($base . '/api/v2/addresses/' . rawurlencode($address) . '/transactions', 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $txs = $r['json']['items'] ?? [];
        $out = [];
        foreach ($txs as $tx) {
            $to = isset($tx['to']['hash']) ? strtolower($tx['to']['hash']) : null;
            $value = (float) ($tx['value'] ?? 0);
            $amount = ($to === strtolower($address)) ? $value : -$value;
            $ts = strtotime($tx['timestamp'] ?? '');
            $out[] = [
                'tx_hash' => $tx['hash'] ?? null, 'block_height' => $tx['block_number'] ?? null,
                'confirmations' => $tx['confirmations'] ?? 0,
                'timestamp' => (is_numeric($ts) ? (int) $ts : null),
                'amount' => $amount,
                'fee' => (isset($tx['fee']['value']) ? (float) $tx['fee']['value'] / 1e18 : 0),
                'from_address' => $tx['from']['hash'] ?? null, 'to_address' => $tx['to']['hash'] ?? null,
                'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsBlockscoutTokenTransfers')) {
    function expl_fetchAddressTxsBlockscoutTokenTransfers($address) {
        $r = httpRequest('https://eth.blockscout.com/api/v2/addresses/' . rawurlencode($address) . '/token-transfers', 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $items = array_filter($r['json']['items'] ?? [], function ($t) {
            return isset($t['token']['symbol']) && $t['token']['symbol'] === 'USDT';
        });
        $out = [];
        foreach ($items as $t) {
            $toAddr = isset($t['to']['hash']) ? strtolower($t['to']['hash']) : null;
            $value = (float) (($t['total']['value'] ?? 0));
            $ts = isset($t['timestamp']) ? strtotime($t['timestamp']) : false;
            $out[] = [
                'tx_hash' => $t['transaction_hash'] ?? null, 'block_height' => $t['block_number'] ?? null,
                'confirmations' => 0, 'timestamp' => (is_numeric($ts) ? (int) $ts : null),
                'amount' => ($toAddr === strtolower($address) ? $value : -$value), 'fee' => 0,
                'from_address' => $t['from']['hash'] ?? null, 'to_address' => $t['to']['hash'] ?? null,
                'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsBlockbookRvn')) {
    function expl_fetchAddressTxsBlockbookRvn($address) {
        $r = httpRequest('https://blockbook.ravencoin.org/api/v2/address/' . rawurlencode($address), 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $txids = array_slice($r['json']['txids'] ?? [], 0, 12);
        if (count($txids) === 0) return [];
        $out = [];
        foreach ($txids as $txid) {
            $t = httpRequest('https://blockbook.ravencoin.org/api/v2/tx/' . rawurlencode($txid), 'GET');
            if ($t === null || !is_array($t['json'])) continue;
            $tx = $t['json'];
            $received = 0.0; $sent = 0.0;
            foreach (($tx['vout'] ?? []) as $o) {
                if (in_array($address, ($o['addresses'] ?? []))) $received += (float) ($o['value'] ?? 0);
            }
            foreach (($tx['vin'] ?? []) as $inp) {
                if (in_array($address, ($inp['addresses'] ?? []))) $sent += (float) ($inp['value'] ?? 0);
            }
            $out[] = [
                'tx_hash' => $tx['txid'] ?? null, 'block_height' => $tx['blockHeight'] ?? null,
                'confirmations' => $tx['confirmations'] ?? 0, 'timestamp' => $tx['blockTime'] ?? null,
                'amount' => $received - $sent, 'fee' => (float) ($tx['fees'] ?? 0),
                'from_address' => $sent > 0 ? $address : null, 'to_address' => $received > 0 ? $address : null,
                'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsKaspa')) {
    function expl_fetchAddressTxsKaspa($address) {
        $r = httpRequest('https://api.kaspa.org/addresses/' . rawurlencode($address) . '/full-transactions', 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $txs = is_array($r['json']) ? $r['json'] : ($r['json']['transactions'] ?? []);
        $out = [];
        foreach ($txs as $tx) {
            $received = 0.0; $sent = 0.0;
            foreach (($tx['outputs'] ?? []) as $o) {
                if (($o['script_public_key_address'] ?? null) === $address) $received += (float) ($o['amount'] ?? 0);
            }
            foreach (($tx['inputs'] ?? []) as $inp) {
                $prev = $inp['previous_transaction_output'] ?? [];
                if (($prev['script_public_key_address'] ?? null) === $address) $sent += (float) ($prev['amount'] ?? 0);
            }
            $out[] = [
                'tx_hash' => $tx['transaction_id'] ?? ($tx['txid'] ?? ($tx['id'] ?? null)),
                'block_height' => null, 'confirmations' => !empty($tx['is_accepted']) ? 1 : 0,
                'timestamp' => isset($tx['block_time']) ? (int) floor((float) $tx['block_time'] / 1000) : null,
                'amount' => $received - $sent, 'fee' => 0,
                'from_address' => $sent > 0 ? $address : null, 'to_address' => $received > 0 ? $address : null,
                'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxsXrp')) {
    function expl_fetchAddressTxsXrp($address) {
        $r = expl_rpcRippled('account_tx', [['account' => $address, 'ledger_index_min' => -1, 'ledger_index_max' => -1, 'limit' => 50, 'binary' => false]]);
        $txs = array_filter($r['transactions'] ?? [], function ($t) {
            return !empty($t['validated']) && isset($t['tx']);
        });
        $out = [];
        foreach ($txs as $t) {
            $tx = $t['tx'];
            $delivered = ($t['meta']['delivered_amount'] ?? null);
            $amountRaw = ($delivered !== null) ? $delivered : ($tx['Amount'] ?? 0);
            $isIssued = is_array($amountRaw);
            $amount = $isIssued ? (float) ($delivered['value'] ?? 0) : (float) $amountRaw;
            $incoming = ($tx['Destination'] ?? null) === $address;
            $out[] = [
                'tx_hash' => $tx['hash'] ?? null, 'block_height' => $tx['ledger_index'] ?? null,
                'confirmations' => !empty($t['validated']) ? 1 : 0, 'timestamp' => null,
                'amount' => $incoming ? $amount : -$amount,
                'fee' => (float) ($tx['Fee'] ?? 0),
                'from_address' => $tx['Account'] ?? null, 'to_address' => $tx['Destination'] ?? null,
                'type' => 'transaction',
            ];
        }
        return $out;
    }
}

if (!function_exists('expl_fetchAddressTxs')) {
    function expl_fetchAddressTxs($address, $coin) {
        $cu = strtoupper((string) $coin);
        if (in_array($cu, ['BTC', 'BTCT', 'LTC'], true)) return expl_fetchAddressTxsMempool($address, expl_apiUrl($cu));
        if (in_array($cu, ['DOGE', 'BCH', 'DASH', 'DGB', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'], true)) return expl_fetchAddressTxsBlockcypher($address, expl_apiUrl($cu));
        if (in_array($cu, ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'], true)) {
            $txs = expl_fetchAddressTxsEtherscan($address, expl_apiUrl($cu));
            if (count($txs) > 0) return $txs;
            $bsTxs = expl_fetchAddressTxsBlockscoutV2($address, 'https://api.etherscan.io');
            if (count($bsTxs) > 0) return $bsTxs;
            if ($cu === 'ETH') return expl_fetchAddressTxsBlockscoutV2($address, 'https://eth.blockscout.com');
            if ($cu === 'USDT') return expl_fetchAddressTxsBlockscoutTokenTransfers($address);
            if ($cu === 'POLYGON') return expl_fetchAddressTxsBlockscoutV2($address, 'https://polygon.blockscout.com');
            if ($cu === 'BSC') return expl_fetchAddressTxsBlockscoutV2($address, 'https://blockscout.com/bsc/mainnet');
            return [];
        }
        if ($cu === 'RVN') return expl_fetchAddressTxsBlockbookRvn($address);
        if ($cu === 'KASPA') return expl_fetchAddressTxsKaspa($address);
        if ($cu === 'XRP') return expl_fetchAddressTxsXrp($address);
        return [];
    }
}

if (!function_exists('expl_btcSubsidySats')) {
    function expl_btcSubsidySats($height) {
        $n = intdiv((int) $height, 210000);
        if ($n > 33) $n = 33;
        $sub = 50.0;
        for ($i = 0; $i < $n; $i++) $sub /= 2;
        return (int) round($sub * 1e8);
    }
}

if (!function_exists('expl_fetchBitcoinBlocks')) {
    function expl_fetchBitcoinBlocks($startHeight = 0, $limit = 10) {
        $url = 'https://mempool.space/api/blocks';
        if ($startHeight > 0) $url .= '?start_height=' . (int) $startHeight;
        $r = httpRequest($url, 'GET');
        if ($r === null || !is_array($r['json'])) return [];
        $blocks = [];
        foreach ($r['json'] as $b) {
            if (!is_array($b)) continue;
            $h = isset($b['height']) ? (int) $b['height'] : null;
            $blocks[] = [
                'id' => $b['id'] ?? null, 'height' => $h,
                'hash' => $b['hash'] ?? $b['id'] ?? null, 'time' => $b['timestamp'] ?? null,
                'timestamp' => $b['timestamp'] ?? null, 'tx_count' => $b['tx_count'] ?? count($b['txids'] ?? []),
                'size' => $b['size'] ?? null, 'weight' => $b['weight'] ?? null,
                'difficulty' => isset($b['difficulty']) ? (float) $b['difficulty'] : null,
                'reward' => ($h !== null) ? expl_btcSubsidySats($h) : 0,
                'fee' => 0,
            ];
            if (count($blocks) >= $limit) break;
        }
        return $blocks;
    }
}

if (!function_exists('expl_fetchBlockByHash')) {
    function expl_fetchBlockByHash($hash) {
        $r = httpRequest('https://mempool.space/api/block/' . rawurlencode($hash), 'GET');
        if ($r === null || !is_array($r['json'])) return null;
        $b = $r['json'];
        $height = isset($b['height']) ? (int) $b['height'] : null;
        $extra = (isset($b['extras']) && is_array($b['extras'])) ? $b['extras'] : [];
        $reward = (isset($extra['reward']) && is_array($extra['reward']) && isset($extra['reward']['total']))
            ? (float) $extra['reward']['total']
            : (($height !== null) ? (float) expl_btcSubsidySats($height) : 0.0);
        $fee = (isset($extra['reward']) && is_array($extra['reward']) && isset($extra['reward']['total'], $extra['reward']['subsidy']))
            ? (float) ($extra['reward']['total'] - $extra['reward']['subsidy'])
            : 0.0;
        return [
            'hash' => $b['hash'] ?? null, 'height' => $height, 'version' => $b['version'] ?? null,
            'timestamp' => $b['timestamp'] ?? null, 'bits' => $b['bits'] ?? null, 'nonce' => $b['nonce'] ?? null,
            'difficulty' => isset($b['difficulty']) ? (float) $b['difficulty'] : null,
            'merkle_root' => $b['merkle_root'] ?? null,
            'size' => $b['size'] ?? null, 'weight' => $b['weight'] ?? null,
            'tx_count' => $b['n_tx'] ?? count($b['tx'] ?? []),
            'n_tx' => $b['n_tx'] ?? count($b['tx'] ?? []),
            'reward' => $reward, 'fee' => $fee, 'transactions' => $b['tx'] ?? [],
        ];
    }
}

if (!function_exists('expl_fetchBitcoinStats')) {
    function expl_fetchBitcoinStats() {
        $height = null; $hash = null; $price = null; $hashrateEh = null; $difficulty = null;
        $tip = httpRequest('https://mempool.space/api/blocks', 'GET');
        if ($tip !== null && is_array($tip['json']) && isset($tip['json'][0])) {
            $t = $tip['json'][0];
            $height = isset($t['height']) ? (int) $t['height'] : null;
            $hash = isset($t['id']) ? (string) $t['id'] : null;
            if (isset($t['difficulty'])) $difficulty = (float) $t['difficulty'];
        }
        $pr = httpRequest('https://mempool.space/api/v1/prices', 'GET');
        if ($pr !== null && is_array($pr['json']) && isset($pr['json']['USD'])) $price = (float) $pr['json']['USD'];
        $hr = httpRequest('https://mempool.space/api/v1/mining/hashrate/1d', 'GET');
        if ($hr !== null && is_array($hr['json'])) {
            $items = array_values($hr['json']);
            if (count($items) > 0 && isset($items[count($items) - 1]['avgHashrate'])) {
                $hashrateEh = (float) $items[count($items) - 1]['avgHashrate'] / 1e18;
            }
        }
        $supply = 0.0;
        if ($height !== null) {
            $n = intdiv((int) $height, 210000);
            for ($i = 0; $i < $n && $i < 33; $i++) {
                $supply += 210000 * 50.0 / pow(2, $i);
            }
            $sub = 50.0;
            for ($i = 0; $i < $n && $i < 33; $i++) $sub /= 2;
            $supply += ((int) $height - $n * 210000) * $sub;
        }
        return [
            'price' => $price, 'hashrate' => $hashrateEh, 'difficulty' => $difficulty,
            'circulating_supply' => $supply, 'height' => $height, 'hash' => $hash,
        ];
    }
}

if (!function_exists('expl_fetchFeeEstimation')) {
    function expl_fetchFeeEstimation($coin) {
        $cu = strtoupper((string) $coin);
        if (in_array($cu, ['BTC', 'BTCT'], true)) {
            $base = $cu === 'BTCT' ? 'https://mempool.space/testnet' : 'https://mempool.space';
            $r = httpRequest($base . '/api/v1/fees/recommended', 'GET');
            if ($r !== null && is_array($r['json']) && isset($r['json']['fastestFee'])) {
                return [
                    'low' => ['fee' => (float) ($r['json']['halfHourFee'] ?? $r['json']['hourFee'] ?? 0), 'time' => '~60 min'],
                    'medium' => ['fee' => (float) ($r['json']['halfHourFee'] ?? 0), 'time' => '~30 min'],
                    'high' => ['fee' => (float) ($r['json']['fastestFee'] ?? 0), 'time' => '~10 min'],
                ];
            }
        }
        if (in_array($cu, ['BTC'], true)) {
            $r = httpRequest('https://blockstream.info/api/fees/recommended', 'GET');
            if ($r !== null && is_array($r['json']) && isset($r['json']['fastest_fee'])) {
                return [
                    'low' => ['fee' => (float) ($r['json']['hour_fee'] ?? 0), 'time' => '~60 min'],
                    'medium' => ['fee' => (float) ($r['json']['half_hour_fee'] ?? 0), 'time' => '~30 min'],
                    'high' => ['fee' => (float) ($r['json']['fastest_fee'] ?? 0), 'time' => '~10 min'],
                ];
            }
        }
        if ($cu === 'LTC') {
            $r = httpRequest('https://litecoinspace.org/api/v1/fees/recommended', 'GET');
            if ($r !== null && is_array($r['json']) && isset($r['json']['fastestFee'])) {
                return [
                    'low' => ['fee' => (float) ($r['json']['hourFee'] ?? 0), 'time' => '~60 min'],
                    'medium' => ['fee' => (float) ($r['json']['halfHourFee'] ?? 0), 'time' => '~30 min'],
                    'high' => ['fee' => (float) ($r['json']['fastestFee'] ?? 0), 'time' => '~10 min'],
                ];
            }
        }
        $blockcypherFeeCoins = ['DOGE', 'BCH', 'DASH', 'DGB', 'RVN', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'];
        if (in_array($cu, $blockcypherFeeCoins)) {
            $coinLower = strtolower($cu);
            $r = httpRequest("https://api.blockcypher.com/v1/{$coinLower}/main", 'GET');
            if ($r !== null && is_array($r['json']) && isset($r['json']['medium_fee_per_byte'])) {
                return [
                    'low' => ['fee' => (float) ($r['json']['low_fee_per_byte'] ?? 1), 'time' => '~60 min'],
                    'medium' => ['fee' => (float) ($r['json']['medium_fee_per_byte'] ?? 3), 'time' => '~30 min'],
                    'high' => ['fee' => (float) ($r['json']['high_fee_per_byte'] ?? 10), 'time' => '~10 min'],
                ];
            }
        }
        $evmFeeApis = [
            'ETH' => ['https://eth.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.etherscan.io/api?module=proxy&action=eth_gasPrice'],
            'ETC' => ['https://etc.blockscout.com/api?module=proxy&action=eth_gasPrice'],
            'USDT' => ['https://eth.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.etherscan.io/api?module=proxy&action=eth_gasPrice'],
            'POLYGON' => ['https://polygon.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.polygonscan.com/api?module=proxy&action=eth_gasPrice'],
            'BSC' => ['https://bsc.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.bscscan.com/api?module=proxy&action=eth_gasPrice'],
        ];
        if (isset($evmFeeApis[$cu])) {
            foreach ($evmFeeApis[$cu] as $feeUrl) {
                $r = httpRequest($feeUrl, 'GET');
                if ($r === null || !is_array($r['json'])) continue;
                $hex = $r['json']['result'] ?? null;
                if (!is_string($hex) || !preg_match('/^0x[0-9a-fA-F]+$/', $hex)) continue;
                $gasGwei = hexdec($hex) / 1e9;
                if ($gasGwei <= 0) continue;
                $txSize = $cu === 'USDT' ? 100000 : 21000;
                $fee = $gasGwei * $txSize / 1e9;
                return [
                    'low' => ['fee' => number_format($fee, 8, '.', ''), 'time' => '~60 min'],
                    'medium' => ['fee' => number_format($fee, 8, '.', ''), 'time' => '~30 min'],
                    'high' => ['fee' => number_format($fee, 8, '.', ''), 'time' => '~10 min'],
                ];
            }
        }
        $f = [
            'ETH' => [10, 20, 50], 'BTC' => [10, 20, 50], 'LTC' => [1, 3, 10], 'DOGE' => [1, 3, 10],
            'BCH' => [1, 3, 10], 'DASH' => [1, 3, 10], 'DGB' => [1, 3, 10], 'RVN' => [1, 2, 8],
            'BTG' => [1, 2, 10], 'ETC' => [1, 3, 10], 'USDT' => [1, 3, 10], 'POLYGON' => [30, 60, 120],
            'BSC' => [3, 5, 10], 'ZEC' => [1, 3, 10], 'BSV' => [1, 3, 10], 'XRP' => [0.00001, 0.000012, 0.000015],
            'XVG' => [1, 2, 5], 'QTUM' => [1, 3, 10], 'VTC' => [1, 3, 10], 'KMD' => [1, 2, 8], 'KASPA' => [1, 2, 5],
        ];
        $evm = ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'];
        $txSize = $cu === 'USDT' ? 100000 : (in_array($cu, $evm) ? 21000 : 250);
        $divisor = in_array($cu, $evm) ? 1e9 : 1e8;
        if (!isset($f[$cu])) { $txSize = 250; $divisor = 1e8; }
        $vals = $f[$cu] ?? [10, 20, 50];
        return [
            'low' => ['fee' => number_format($vals[0] * $txSize / $divisor, 8, '.', ''), 'time' => '~60 min'],
            'medium' => ['fee' => number_format($vals[1] * $txSize / $divisor, 8, '.', ''), 'time' => '~30 min'],
            'high' => ['fee' => number_format($vals[2] * $txSize / $divisor, 8, '.', ''), 'time' => '~10 min'],
        ];
    }
}

if (!function_exists('expl_parseMempoolTx')) {
    function expl_parseMempoolTx($tx, $address) {
        $received = 0.0; $sent = 0.0;
        foreach (($tx['vout'] ?? []) as $o) {
            $pka = $o['scriptpubkey_address'] ?? ($o['scriptPubKey']['addresses'][0] ?? null);
            if ($pka === $address) $received += (float) ($o['value'] ?? 0);
        }
        foreach (($tx['vin'] ?? []) as $inp) {
            $prev = $inp['prevout'] ?? [];
            $pka = $prev['scriptpubkey_address'] ?? ($prev['scriptPubKey']['addresses'][0] ?? null);
            if ($pka === $address) $sent += (float) ($prev['value'] ?? 0);
        }
        return $received - $sent;
    }
}

if (!function_exists('expl_fetchTxDetail')) {
    function expl_fetchTxDetail($hash, $coin) {
        $cu = strtoupper((string) $coin);
        $address = isset($_GET['address']) ? (string) $_GET['address'] : null;
        if (in_array($cu, ['BTC', 'BTCT', 'LTC'], true)) {
            $base = ($cu === 'LTC') ? 'https://litecoinspace.org' : ($cu === 'BTCT' ? 'https://mempool.space/testnet' : 'https://mempool.space');
            $r = httpRequest($base . '/api/tx/' . rawurlencode($hash), 'GET');
            if ($r === null || !is_array($r['json'])) return null;
            $tx = $r['json'];
            $vout = [];
            foreach (($tx['vout'] ?? []) as $o) {
                $vout[] = ['scriptpubkey_address' => $o['scriptpubkey_address'] ?? null, 'value' => (float) ($o['value'] ?? 0)];
            }
            $vin = [];
            foreach (($tx['vin'] ?? []) as $inp) {
                $pka = isset($inp['prevout']['scriptpubkey_address']) ? $inp['prevout']['scriptpubkey_address'] : null;
                $vin[] = ['scriptpubkey_address' => $pka, 'value' => (float) ($inp['prevout']['value'] ?? 0)];
            }
            $amount = 0.0; $type = 'unknown';
            if ($address) {
                $amount = expl_parseMempoolTx($tx, $address);
                $type = $amount > 0 ? 'incoming' : 'outgoing';
            }
            return ['success' => true, 'tx_hash' => $tx['txid'] ?? null, 'coin' => $cu, 'block_height' => $tx['status']['block_height'] ?? null,
                'confirmations' => $tx['status']['confirmed'] ? ($tx['status']['confirmations'] ?? 0) : 0,
                'timestamp' => $tx['status']['block_time'] ?? null, 'amount' => $amount, 'type' => $type,
                'inputs' => $vin, 'outputs' => $vout, 'txUrl' => expl_txUrl($cu, $hash)];
        }
        if (in_array($cu, ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'], true)) {
            $base = ($cu === 'ETH') ? 'https://api.etherscan.io' : ($cu === 'ETC' ? 'https://api.etcdesktopwallet.com' :
                ($cu === 'USDT' ? 'https://api.etherscan.io' : ($cu === 'POLYGON' ? 'https://api.polygonscan.com' : 'https://api.bscscan.com')));
            $module = $cu === 'USDT' ? 'account&action=tokentx' : 'proxy&action=eth_getTransactionByHash';
            $r = httpRequest($base . '/api?module=' . $module . '&txhash=' . rawurlencode($hash) . '&apikey=' . (env('ETHERSCAN_API_KEY') ?: ''), 'GET');
            if ($r === null || !is_array($r['json'])) return null;
            $res = $r['json']['result'] ?? null;
            if ($res === '0x' || is_string($res) || (is_array($res) && count($res) === 0)) return null;
            $tx = $res;
            $txObj = (isset($tx['from']) || isset($tx['hash'])) ? $tx : (isset($tx[0]) ? $tx[0] : null);
            if ($txObj === null) return null;
            $value = (float) hexdec($txObj['value'] ?? '0');
            if ($cu === 'USDT') $value = (float) ($txObj['value'] ?? 0) / 1e6;
            $from = $txObj['from'] ?? null;
            $to = $txObj['to'] ?? null;
            $amount = 0.0; $type = 'unknown';
            if ($address) {
                $amount = ($to && strtolower($to) === strtolower($address)) ? $value : -$value;
                $type = $amount > 0 ? 'incoming' : 'outgoing';
            }
            return ['success' => true, 'tx_hash' => $txObj['hash'] ?? $hash, 'coin' => $cu, 'block_height' => isset($txObj['blockNumber']) ? (int) hexdec($txObj['blockNumber']) : null,
                'confirmations' => 0, 'timestamp' => 0, 'amount' => $amount, 'type' => $type,
                'inputs' => [['scriptpubkey_address' => $from, 'value' => $value]], 'outputs' => [['scriptpubkey_address' => $to, 'value' => $value]], 'txUrl' => expl_txUrl($cu, $hash)];
        }
        if (in_array($cu, ['DOGE', 'BCH', 'DASH', 'DGB', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'], true)) {
            $chainMap = ['DOGE'=>'doge','BCH'=>'bch','DASH'=>'dash','DGB'=>'dgb','BTG'=>'btg','ZEC'=>'zec','BSV'=>'bsv','XVG'=>'xvg','QTUM'=>'qtum','VTC'=>'vtc','KMD'=>'kmd'];
            $r = httpRequest('https://api.blockcypher.com/v1/' . $chainMap[$cu] . '/main/txs/' . rawurlencode($hash) . '?includeScript=false', 'GET');
            if ($r === null || !is_array($r['json'])) return null;
            $tx = $r['json'];
            $vin = []; $vout = [];
            foreach (($tx['inputs'] ?? []) as $inp) foreach (($inp['addresses'] ?? []) as $a) $vin[] = ['scriptpubkey_address' => $a];
            foreach (($tx['outputs'] ?? []) as $o) foreach (($o['addresses'] ?? []) as $a) $vout[] = ['scriptpubkey_address' => $a, 'value' => (int) ($o['value'] ?? 0)];
            return ['success' => true, 'tx_hash' => $tx['hash'] ?? $hash, 'coin' => $cu, 'block_height' => $tx['block_height'] ?? null,
                'confirmations' => (int) ($tx['confirmations'] ?? 0), 'timestamp' => strtotime($tx['confirmed'] ?? ''),
                'amount' => 0.0, 'type' => 'transaction', 'inputs' => $vin, 'outputs' => $vout, 'txUrl' => expl_txUrl($cu, $hash)];
        }
        if ($cu === 'RVN') {
            $r = httpRequest('https://blockbook.ravencoin.org/api/v2/tx/' . rawurlencode($hash), 'GET');
            if ($r === null || !is_array($r['json'])) return null;
            $tx = $r['json'];
            return ['success' => true, 'tx_hash' => $tx['txid'] ?? $hash, 'coin' => $cu, 'block_height' => $tx['blockHeight'] ?? null,
                'confirmations' => (int) ($tx['confirmations'] ?? 0), 'timestamp' => $tx['blockTime'] ?? null, 'amount' => 0.0, 'type' => 'transaction',
                'inputs' => $tx['vin'] ?? [], 'outputs' => $tx['vout'] ?? [], 'txUrl' => expl_txUrl($cu, $hash)];
        }
        if ($cu === 'KASPA') {
            $r = httpRequest('https://api.kaspa.org/transactions/' . rawurlencode($hash), 'GET');
            if ($r === null || !is_array($r['json'])) return null;
            $tx = is_array($r['json']) ? $r['json'] : ($r['json']['transaction'] ?? []);
            return ['success' => true, 'tx_hash' => $tx['transaction_id'] ?? $hash, 'coin' => $cu, 'block_height' => $tx['block_height'] ?? null,
                'confirmations' => 0, 'timestamp' => isset($tx['block_time']) ? (int) floor((float) $tx['block_time'] / 1000) : 0,
                'amount' => 0.0, 'type' => 'transaction', 'inputs' => $tx['inputs'] ?? [], 'outputs' => $tx['outputs'] ?? [], 'txUrl' => expl_txUrl($cu, $hash)];
        }
        if ($cu === 'XRP') {
            $rr = expl_rpcRippled('tx', [['transaction' => $hash]]);
            if (!isset($rr['tx']) && !isset($rr['transaction'])) return null;
            $tx = $rr['tx'] ?? $rr['transaction'];
            $dt = $rr['meta'] ?? [];
            $delivered = $dt['delivered_amount'] ?? null;
            $value = is_array($delivered) ? (float) ($delivered['value'] ?? 0) : (float) ($delivered ?? 0);
            $from = $tx['Account'] ?? null; $to = $tx['Destination'] ?? null;
            $amount = ($to && $address && strtolower($to) === strtolower($address)) ? $value : -$value;
            return ['success' => true, 'tx_hash' => $tx['hash'] ?? $hash, 'coin' => $cu, 'block_height' => $tx['ledger_index'] ?? null,
                'confirmations' => !empty($rr['validated']) ? 1 : 0, 'timestamp' => 0, 'amount' => $amount, 'type' => 'transaction',
                'inputs' => [['scriptpubkey_address' => $from]], 'outputs' => [['scriptpubkey_address' => $to, 'value' => $value]], 'txUrl' => expl_txUrl($cu, $hash)];
        }
        return null;
    }
}

if (!function_exists('expl_fetchAddressSummary')) {
    function expl_fetchAddressSummary($address, $coin) {
        $cu = strtoupper((string) $coin);
        try {
            if (in_array($cu, ['BTC', 'BTCT', 'LTC'], true)) {
                $base = ($cu === 'LTC') ? 'https://litecoinspace.org' : ($cu === 'BTCT' ? 'https://mempool.space/testnet' : 'https://mempool.space');
                $r = httpRequest($base . '/api/address/' . rawurlencode($address), 'GET');
                if ($r === null || !is_array($r['json'])) return null;
                $d = $r['json'];
                $c = $d['chain_stats'] ?? []; $m = $d['mempool_stats'] ?? [];
                return [
                    'balance' => ((float)($c['funded_txo_sum'] ?? 0) - (float)($c['spent_txo_sum'] ?? 0)) + ((float)($m['funded_txo_sum'] ?? 0) - (float)($m['spent_txo_sum'] ?? 0)),
                    'unconfirmed' => ((float)($m['funded_txo_sum'] ?? 0) - (float)($m['spent_txo_sum'] ?? 0)),
                    'total_received' => (float)($c['funded_txo_sum'] ?? 0), 'total_sent' => (float)($c['spent_txo_sum'] ?? 0),
                    'tx_count' => ((int)($c['tx_count'] ?? 0)) + ((int)($m['tx_count'] ?? 0)),
                ];
            }
            if (in_array($cu, ['DOGE', 'BCH', 'DASH', 'DGB', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'], true)) {
                $bcMap = ['DOGE'=>'doge','BCH'=>'bch','DASH'=>'dash','DGB'=>'dgb','BTG'=>'btg','ZEC'=>'zec','BSV'=>'bsv','XVG'=>'xvg','QTUM'=>'qtum','VTC'=>'vtc','KMD'=>'kmd'];
                $r = httpRequest('https://api.blockcypher.com/v1/' . $bcMap[$cu] . '/main/addrs/' . rawurlencode($address), 'GET');
                if ($r === null || !is_array($r['json'])) return null;
                $d = $r['json'];
                return ['balance' => (float)($d['balance'] ?? 0), 'unconfirmed' => (float)($d['unconfirmed_balance'] ?? 0),
                    'total_received' => (float)($d['total_received'] ?? 0), 'total_sent' => (float)($d['total_sent'] ?? 0), 'tx_count' => (int)($d['n_tx'] ?? 0)];
            }
            if ($cu === 'KASPA') {
                $r = httpRequest('https://api.kaspa.org/addresses/' . rawurlencode($address) . '/balance', 'GET');
                if ($r === null || !is_array($r['json'])) return null;
                return ['balance' => (float)($r['json']['balance'] ?? 0), 'unconfirmed' => 0, 'total_received' => null, 'total_sent' => null, 'tx_count' => null];
            }
            if ($cu === 'XRP') {
                $rr = expl_rpcRippled('account_info', [['account' => $address, 'ledger_index' => 'validated']]);
                if (!isset($rr['account_data'])) return null;
                return ['balance' => (float)($rr['account_data']['Balance'] ?? 0), 'unconfirmed' => 0, 'total_received' => null, 'total_sent' => null, 'tx_count' => null];
            }
            if (in_array($cu, ['POLYGON', 'BSC'], true)) {
                $rpc = ($cu === 'POLYGON') ? 'https://1rpc.io/matic' : 'https://bsc-dataseed1.binance.org';
                $r = httpRequest($rpc, 'POST', json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_getBalance', 'params' => [$address, 'latest']]), ['Content-Type: application/json']);
                if ($r === null || !is_array($r['json'])) return null;
                return ['balance' => isset($r['json']['result']) ? (int)hexdec($r['json']['result']) : 0, 'unconfirmed' => 0, 'total_received' => null, 'total_sent' => null, 'tx_count' => null];
            }
            if ($cu === 'USDT') {
                $r = httpRequest('https://eth.blockscout.com/api/v2/addresses/' . rawurlencode($address) . '/token-balances', 'GET');
                if ($r === null || !is_array($r['json'])) return null;
                $item = null;
                foreach ($r['json'] as $t) if (isset($t['token']['symbol']) && $t['token']['symbol'] === 'USDT') { $item = $t; break; }
                return ['balance' => $item ? ((float)($item['value'] ?? 0) / 1e6) : 0, 'unconfirmed' => 0, 'total_received' => null, 'total_sent' => null, 'tx_count' => null];
            }
            if ($cu === 'RVN') {
                $r = httpRequest('https://blockbook.ravencoin.org/api/v2/address/' . rawurlencode($address), 'GET');
                if ($r === null || !is_array($r['json'])) return null;
                $d = $r['json'];
                return ['balance' => (float)($d['balance'] ?? 0), 'unconfirmed' => (float)($d['unconfirmedBalance'] ?? 0), 'total_received' => (float)($d['totalReceived'] ?? 0), 'total_sent' => (float)($d['totalSent'] ?? 0), 'tx_count' => (int)($d['txs'] ?? 0)];
            }
            if (in_array($cu, ['ETH', 'ETC'], true)) {
                $base = ($cu === 'ETC') ? 'https://etc.blockscout.com' : 'https://eth.blockscout.com';
                $r = httpRequest($base . '/api/v2/addresses/' . rawurlencode($address), 'GET');
                if ($r === null || !is_array($r['json'])) return null;
                return ['balance' => isset($r['json']['coin_balance']) ? (float)$r['json']['coin_balance'] : 0, 'unconfirmed' => 0, 'total_received' => null, 'total_sent' => null, 'tx_count' => null];
            }
            return null;
        } catch (Throwable $e) {
            error_log('Address summary error for ' . $cu . ': ' . $e->getMessage());
            return null;
        }
    }
}
