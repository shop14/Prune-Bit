<?php
function feeFallback($coin) {
    $cu = strtoupper((string) $coin);
    $defaults = [
        'ETH' => ['slow' => 10, 'medium' => 20, 'fast' => 50],
        'BTC' => ['slow' => 10, 'medium' => 20, 'fast' => 50],
        'BTCT' => ['slow' => 10, 'medium' => 20, 'fast' => 50],
        'LTC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'DOGE' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'BCH' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'DASH' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'DGB' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'RVN' => ['slow' => 1, 'medium' => 2, 'fast' => 8],
        'BTG' => ['slow' => 1, 'medium' => 2, 'fast' => 10],
        'ETC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'USDT' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'POLYGON' => ['slow' => 30, 'medium' => 60, 'fast' => 120],
        'BSC' => ['slow' => 3, 'medium' => 5, 'fast' => 10],
        'ZEC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'BSV' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'XVG' => ['slow' => 1, 'medium' => 2, 'fast' => 5],
        'QTUM' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'VTC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
        'KMD' => ['slow' => 1, 'medium' => 2, 'fast' => 8],
        'KASPA' => ['slow' => 1, 'medium' => 2, 'fast' => 5],
        'XRP' => ['slow' => 0.00001, 'medium' => 0.000012, 'fast' => 0.000015],
    ];
    $evm = ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'];
    $txSize = in_array($cu, $evm) ? ($cu === 'USDT' ? 100000 : 21000) : 250;
    $divisor = in_array($cu, $evm) ? 1e9 : 1e8;
    $f = $defaults[$cu] ?? $defaults['BTC'];
    if ($cu === 'XRP') {
        return ['success' => true, 'fees' => [
            'low' => ['fee' => number_format((float) $f['slow'], 8, '.', ''), 'time' => '~60 min'],
            'medium' => ['fee' => number_format((float) $f['medium'], 8, '.', ''), 'time' => '~30 min'],
            'high' => ['fee' => number_format((float) $f['fast'], 8, '.', ''), 'time' => '~10 min'],
        ]];
    }
    return ['success' => true, 'fees' => [
        'low' => ['fee' => number_format($f['slow'] * $txSize / $divisor, 8, '.', ''), 'time' => '~60 min'],
        'medium' => ['fee' => number_format($f['medium'] * $txSize / $divisor, 8, '.', ''), 'time' => '~30 min'],
        'high' => ['fee' => number_format($f['fast'] * $txSize / $divisor, 8, '.', ''), 'time' => '~10 min'],
    ]];
}

function feeCryptoApi($coin) {
    $cu = strtoupper((string) $coin);
    $blockchain = $cu === 'BTCT' ? 'bitcoin' : strtolower($cu);
    $endpoint = 'https://rest.cryptoapis.io/blockchain-fees/utxo/' . $blockchain . '/mainnet/mempool';
    $key = env('CRYPTOAPIS_API_KEY') ?: '';
    if (!$key) return null;
    $resp = httpRequest($endpoint, 'GET', null, ['X-API-Key: ' . $key, 'Content-Type: application/json']);
    if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) return null;
    $data = $resp['json'];
    if (!is_array($data)) return null;
    $fees = $data['payload'] ?? [];
    $evm = ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'];
    $divisor = in_array($cu, $evm) ? 1e9 : 1e8;
    $txSize = $cu === 'USDT' ? 100000 : (in_array($cu, $evm) ? 21000 : 250);
    $res = feeFallback($cu);
    $res['fees']['low']['fee'] = number_format((float) ($fees['slow'] ?? $fees['low'] ?? 1) * $txSize / $divisor, 8, '.', '');
    $res['fees']['medium']['fee'] = number_format((float) ($fees['standard'] ?? $fees['medium'] ?? 5) * $txSize / $divisor, 8, '.', '');
    $res['fees']['high']['fee'] = number_format((float) ($fees['fast'] ?? $fees['high'] ?? 15) * $txSize / $divisor, 8, '.', '');
    return $res;
}

try {
    Api::requirePost();

    $coin = Api::body('coin');
    if (!$coin) {
        jsonResponse(['error' => 'Coin is required'], 400);
    }
    $coinUpper = strtoupper((string) $coin);

    if ($coinUpper === 'BTC' || $coinUpper === 'LTC') {
        try {
            require_once __DIR__ . '/../../classes/ElectrumClient.php';
            if (ElectrumClient::isAvailable()) {
                $electrum = new ElectrumClient($coinUpper);
                $electrum->discoverServers();
                $fastFee = $electrum->estimateFee(1);
                $mediumFee = $electrum->estimateFee(5);
                $slowFee = $electrum->estimateFee(10);
                $txSize = 250;
                $toBtc = function ($btcPerKb) use ($txSize) { return $btcPerKb * $txSize / 1000; };
                jsonResponse([
                    'success' => true,
                    'fees' => [
                        'low' => ['fee' => number_format($toBtc($slowFee ?? 0.0001), 8, '.', ''), 'time' => '~60 min'],
                        'medium' => ['fee' => number_format($toBtc($mediumFee ?? 0.0002), 8, '.', ''), 'time' => '~30 min'],
                        'high' => ['fee' => number_format($toBtc($fastFee ?? 0.0005), 8, '.', ''), 'time' => '~10 min'],
                    ],
                ]);
            }
        } catch (Throwable $e) {
            error_log('Electrum fee error for ' . $coinUpper . ': ' . $e->getMessage());
        }
    }

    $feeApis = [
        'BTC' => 'https://mempool.space/api/v1/fees/recommended',
        'BTCT' => 'https://mempool.space/testnet/api/v1/fees/recommended',
        'LTC' => 'https://litecoinspace.org/api/v1/fees/recommended',
    ];
    $feeUrl = $feeApis[$coinUpper] ?? null;
    if ($feeUrl) {
        try {
            $resp = httpRequest($feeUrl, 'GET');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = $resp['json'];
                if (is_array($data)) {
                    $txSize = 250;
                    $divisor = 1e8;
                    jsonResponse(['success' => true, 'fees' => [
                        'low' => ['fee' => number_format(((float)($data['hourFee'] ?? 10)) * $txSize / $divisor, 8, '.', ''), 'time' => '~60 min'],
                        'medium' => ['fee' => number_format(((float)($data['halfHourFee'] ?? 20)) * $txSize / $divisor, 8, '.', ''), 'time' => '~30 min'],
                        'high' => ['fee' => number_format(((float)($data['fastestFee'] ?? 100)) * $txSize / $divisor, 8, '.', ''), 'time' => '~10 min'],
                    ]]);
                }
            }
        } catch (Throwable $e) {}
    }

    $blockstreamFees = [
        'BTC' => 'https://blockstream.info/api/fees/recommended',
    ];
    $bsUrl = $blockstreamFees[$coinUpper] ?? null;
    if ($bsUrl) {
        try {
            $resp = httpRequest($bsUrl, 'GET');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = $resp['json'];
                if (is_array($data)) {
                    $txSize = 250;
                    $divisor = 1e8;
                    jsonResponse(['success' => true, 'fees' => [
                        'low' => ['fee' => number_format(((float)($data['hour_fee'] ?? 10)) * $txSize / $divisor, 8, '.', ''), 'time' => '~60 min'],
                        'medium' => ['fee' => number_format(((float)($data['half_hour_fee'] ?? 20)) * $txSize / $divisor, 8, '.', ''), 'time' => '~30 min'],
                        'high' => ['fee' => number_format(((float)($data['fastest_fee'] ?? 100)) * $txSize / $divisor, 8, '.', ''), 'time' => '~10 min'],
                    ]]);
                }
            }
        } catch (Throwable $e) {}
    }

    $blockcypherFeeCoins = ['DOGE', 'BCH', 'DASH', 'DGB', 'RVN', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'];
    $blockscoutFeeCoins = [
        'DOGE' => 'https://doge.blockscout.com/api?module=proxy&action=eth_gasPrice',
        'BCH' => 'https://bch.blockscout.com/api?module=proxy&action=eth_gasPrice',
        'DASH' => 'https://dash.blockscout.com/api?module=proxy&action=eth_gasPrice',
        'DGB' => 'https://dgb.blockscout.com/api?module=proxy&action=eth_gasPrice',
        'BTG' => 'https://btg.blockscout.com/api?module=proxy&action=eth_gasPrice',
        'ZEC' => 'https://zcash.blockscout.com/api?module=proxy&action=eth_gasPrice',
        'QTUM' => 'https://qtum.blockscout.com/api?module=proxy&action=eth_gasPrice',
    ];
    if (isset($blockscoutFeeCoins[$coinUpper])) {
        try {
            $resp = httpRequest($blockscoutFeeCoins[$coinUpper], 'GET');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = $resp['json'];
                if (is_array($data)) {
                    $hex = $data['result'] ?? null;
                    if (is_string($hex) && preg_match('/^0x[0-9a-fA-F]+$/', $hex)) {
                        $gwei = hexdec($hex) / 1e9;
                        if ($gwei > 0) {
                            $txSize = 250;
                            jsonResponse(['success' => true, 'fees' => [
                                'low' => ['fee' => number_format($gwei * $txSize / 1e9, 8, '.', ''), 'time' => '~60 min'],
                                'medium' => ['fee' => number_format($gwei * $txSize / 1e9, 8, '.', ''), 'time' => '~30 min'],
                                'high' => ['fee' => number_format($gwei * $txSize / 1e9, 8, '.', ''), 'time' => '~10 min'],
                            ]]);
                        }
                    }
                }
            }
        } catch (Throwable $e) {}
    }
    if (in_array($coinUpper, $blockcypherFeeCoins)) {
        $coinLower = strtolower($coinUpper);
        try {
            $resp = httpRequest("https://api.blockcypher.com/v1/{$coinLower}/main", 'GET');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = $resp['json'];
                if (is_array($data) && isset($data['medium_fee_per_byte'])) {
                    $txSize = 250;
                    $divisor = 1e8;
                    $low = max(1, (float)($data['low_fee_per_byte'] ?? 1));
                    $med = max(1, (float)($data['medium_fee_per_byte'] ?? 3));
                    $high = max(1, (float)($data['high_fee_per_byte'] ?? 10));
                    jsonResponse(['success' => true, 'fees' => [
                        'low' => ['fee' => number_format($low * $txSize / $divisor, 8, '.', ''), 'time' => '~60 min'],
                        'medium' => ['fee' => number_format($med * $txSize / $divisor, 8, '.', ''), 'time' => '~30 min'],
                        'high' => ['fee' => number_format($high * $txSize / $divisor, 8, '.', ''), 'time' => '~10 min'],
                    ]]);
                }
            }
        } catch (Throwable $e) {}
    }

    $evmFeeEndpoints = [
        'ETH' => ['https://eth.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.etherscan.io/api?module=proxy&action=eth_gasPrice'],
        'ETC' => ['https://etc.blockscout.com/api?module=proxy&action=eth_gasPrice'],
        'USDT' => ['https://eth.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.etherscan.io/api?module=proxy&action=eth_gasPrice'],
        'POLYGON' => ['https://polygon.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.polygonscan.com/api?module=proxy&action=eth_gasPrice'],
        'BSC' => ['https://bsc.blockscout.com/api?module=proxy&action=eth_gasPrice', 'https://api.bscscan.com/api?module=proxy&action=eth_gasPrice'],
    ];
    $evmEndpoints = $evmFeeEndpoints[$coinUpper] ?? null;
    if ($evmEndpoints) {
        foreach ($evmEndpoints as $feeUrl) {
            try {
                $resp = httpRequest($feeUrl, 'GET');
                if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) continue;
                $data = $resp['json'];
                if (!is_array($data)) continue;
                $hex = $data['result'] ?? null;
                if (!is_string($hex) || !preg_match('/^0x[0-9a-fA-F]+$/', $hex)) continue;
                $gasGwei = hexdec($hex) / 1e9;
                if ($gasGwei <= 0) continue;
                $txSize = $coinUpper === 'USDT' ? 100000 : 21000;
                $fee = number_format($gasGwei * $txSize * 1e-9, 8, '.', '');
                jsonResponse(['success' => true, 'fees' => [
                    'low' => ['fee' => $fee, 'time' => '~60 min'],
                    'medium' => ['fee' => $fee, 'time' => '~30 min'],
                    'high' => ['fee' => $fee, 'time' => '~10 min'],
                ]]);
            } catch (Throwable $e) {}
        }
    }

    $cryptoKey = env('CRYPTOAPIS_API_KEY') ?: '';
    if ($cryptoKey && $coinUpper === 'BTC') {
        try {
            $feeData = feeCryptoApi($coinUpper);
            if ($feeData !== null) {
                jsonResponse($feeData);
            }
        } catch (Throwable $e) {}
    }

    jsonResponse(feeFallback($coinUpper));
} catch (Throwable $e) {
    error_log('Fee estimation error: ' . $e->getMessage());
    jsonResponse(feeFallback(Api::body('coin') ?: 'BTC'));
}
