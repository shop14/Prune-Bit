<?php

class BlockchainAPI {
    private static $settings = [
        'BTC' => ['api' => 'https://mempool.space/api'],
        'BTCT' => ['api' => 'https://mempool.space/testnet/api'],
        'LTC' => ['api' => 'https://litecoinspace.org/api'],
        'DOGE' => ['api' => 'https://api.blockcypher.com/v1/doge/main'],
        'BCH' => ['api' => 'https://api.blockcypher.com/v1/bch/main'],
        'RVN' => ['api' => 'https://api.blockcypher.com/v1/rvn/main'],
        'DASH' => ['api' => 'https://api.blockcypher.com/v1/dash/main'],
        'DGB' => ['api' => 'https://api.blockcypher.com/v1/dgb/main'],
        'BTG' => ['api' => 'https://api.blockcypher.com/v1/btg/main'],
        'ZEC' => ['api' => 'https://api.blockcypher.com/v1/zec/main'],
        'BSV' => ['api' => 'https://api.blockcypher.com/v1/bsv/main'],
        'XVG' => ['api' => 'https://api.blockcypher.com/v1/xvg/main'],
        'QTUM' => ['api' => 'https://api.blockcypher.com/v1/qtum/main'],
        'VTC' => ['api' => 'https://api.blockcypher.com/v1/vtc/main'],
        'KMD' => ['api' => 'https://api.blockcypher.com/v1/kmd/main'],
        'KASPA' => ['api' => 'https://api.kaspa.org'],
    ];

    private static function resolveApiUrl($coin, $default) {
        $envKey = 'EXPLORER_' . strtoupper($coin) . '_API';
        $url = env($envKey);
        if ($url !== false && $url !== '' && $url !== null) {
            return rtrim($url, '/');
        }
        return $default;
    }

    private static function getSettings() {
        static $resolved = null;
        if ($resolved === null) {
            $resolved = [];
            foreach (self::$settings as $coin => $cfg) {
                $resolved[$coin] = ['api' => self::resolveApiUrl($coin, $cfg['api'])];
            }
            // EVM chains
            $resolved['ETH'] = ['api' => self::resolveApiUrl('ETH', 'https://eth.blockscout.com')];
            $resolved['ETC'] = ['api' => self::resolveApiUrl('ETC', 'https://etc.blockscout.com')];
            $resolved['POLYGON'] = ['api' => self::resolveApiUrl('POLYGON', 'https://polygon.blockscout.com')];
            $resolved['BSC'] = ['api' => self::resolveApiUrl('BSC', 'https://bsc.blockscout.com')];
            $resolved['USDT'] = ['api' => self::resolveApiUrl('USDT', 'https://eth.blockscout.com')];
        }
        return $resolved;
    }

    private static function httpRequest($url, $method = 'GET', $body = null, $headers = [], $timeout = 8) {
        if (function_exists('blockcypherTokenUrl')) {
            $url = blockcypherTokenUrl($url);
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if (count($headers) > 0) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            return null;
        }
        return ['status' => $httpCode, 'body' => $response];
    }

    private static function httpGet($url, $headers = []) {
        return self::httpRequest($url, 'GET', null, $headers);
    }

    private static function httpPost($url, $body, $headers = []) {
        return self::httpRequest($url, 'POST', $body, $headers);
    }

    private static function decode($resp) {
        if ($resp === null) return null;
        $data = json_decode($resp['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
        return $data;
    }

    private static function normalizeBalance($value, $coin = 'BTC') {
        $amount = (float)($value ?? 0);
        $coinUpper = strtoupper($coin);
        if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'DOGE', 'BCH', 'DASH', 'DGB', 'RVN', 'BTG', 'ZEC', 'BSV', 'QTUM', 'VTC', 'KMD', 'XVG'])) {
            return $amount / 100000000;
        }
        if ($coinUpper === 'USDT') return $amount / 1000000;
        if (in_array($coinUpper, ['ETC', 'POLYGON', 'BSC'])) return $amount / 1e18;
        if ($coinUpper === 'XRP') return $amount / 1000000;
        return $amount;
    }

    private static function getFromBlockchair($chain, $endpoint, $timeout = 10) {
        $url = "https://api.blockchair.com/{$chain}/dashboards/{$endpoint}";
        $resp = self::httpGet($url, [], $timeout);
        $json = self::decode($resp);
        if ($json === null || !isset($json['data'])) return null;
        return $json['data'];
    }

    public static function getBalance($address, $coin = 'BTC') {
        $coinUpper = strtoupper($coin);

        if ($coinUpper === 'USDT') {
            $usdtContract = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
            $data = '0x70a08231' . str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
            $usdtEndpoints = [
                'https://eth.blockscout.com/api?module=proxy&action=eth_call&to=' . $usdtContract . '&data=' . $data . '&tag=latest',
                'https://api.etherscan.io/api?module=proxy&action=eth_call&to=' . $usdtContract . '&data=' . $data . '&tag=latest',
            ];
            foreach ($usdtEndpoints as $usdtUrl) {
                $resp = self::httpGet($usdtUrl);
                $json = self::decode($resp);
                if ($json === null) continue;
                $result = $json['result'] ?? null;
                if (!is_string($result) || !preg_match('/^0x[0-9a-fA-F]{1,64}$/', $result)) continue;
                $raw = hexdec($result);
                if ($raw <= 0) continue;
                return ['balance' => $raw / 1e6, 'unconfirmed_balance' => 0, 'address' => $address];
            }
            return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
        }

        if ($coinUpper === 'POLYGON' || $coinUpper === 'BSC') {
            $scan = $coinUpper === 'POLYGON' ? 'api.polygonscan.com' : 'api.bscscan.com';
            $scanFallback = $coinUpper === 'POLYGON' ? 'https://polygon.blockscout.com' : 'https://bsc.blockscout.com';
            $scanEndpoints = [
                "https://{$scan}/api?module=account&action=balance&address={$address}&tag=latest",
                $scanFallback . '/api?module=account&action=balance&address=' . $address,
            ];
            foreach ($scanEndpoints as $scanUrl) {
                $resp = self::httpGet($scanUrl);
                $json = self::decode($resp);
                if ($json === null) continue;
                if (($json['status'] ?? '') === '1' && isset($json['result']) && is_numeric($json['result'])) {
                    return ['balance' => (float)$json['result'] / 1e18, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
            return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
        }

        if ($coinUpper === 'ETH') {
            $ethEndpoints = [
                "https://api.etherscan.io/api?module=account&action=balance&address={$address}&tag=latest",
                "https://eth.blockscout.com/api?module=account&action=balance&address={$address}",
                "https://api.blockchain.com/v3/explorer/addrs/{$address}",
                "https://api.blockcypher.com/v1/eth/main/addrs/{$address}/balance",
            ];
            foreach ($ethEndpoints as $ethUrl) {
                $resp = self::httpGet($ethUrl);
                $json = self::decode($resp);
                if ($json === null) continue;
                if (isset($json['result']) && ($json['status'] ?? '') === '1') {
                    return ['balance' => (float)$json['result'] / 1e18, 'unconfirmed_balance' => 0, 'address' => $address];
                }
                if (isset($json['address']['balance'])) {
                    return ['balance' => (float)$json['address']['balance'] / 1e18, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
            $data = self::getFromBlockchair('ethereum', "address/{$address}");
            if ($data !== null && isset($data['address'])) {
                $addr = $data['address'];
                $balance = (float)($addr['balance'] ?? '0') / 1e18;
                if ($balance > 0 || isset($addr['transaction_count'])) {
                    return ['balance' => $balance, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
            return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
        }

        if ($coinUpper === 'ETC') {
            $etcEndpoints = [
                "https://etc.blockscout.com/api?module=account&action=balance&address={$address}",
                "https://api.blockscout.com/etc/mainnet/api?module=account&action=balance&address={$address}",
                "https://api.blockcypher.com/v1/etc/main/addrs/{$address}/balance",
            ];
            foreach ($etcEndpoints as $etcUrl) {
                $resp = self::httpGet($etcUrl);
                $json = self::decode($resp);
                if ($json !== null && ($json['status'] ?? '') === '1' && isset($json['result'])) {
                    return ['balance' => (float)$json['result'] / 1e18, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
            return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
        }

        if ($coinUpper === 'KASPA') {
            $kasEndpoints = [
                "https://api.kaspa.org/addresses/{$address}/balance",
            ];
            foreach ($kasEndpoints as $kasUrl) {
                $resp = self::httpGet($kasUrl);
                $json = self::decode($resp);
                if ($json === null) continue;
                if (isset($json['balance'])) {
                    return ['balance' => (float)$json['balance'] / 1e8, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
            return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
        }

        if ($coinUpper === 'XRP') {
            $resp = self::httpPost('https://xrplcluster.com', json_encode([
                'method' => 'account_info',
                'params' => [['account' => $address, 'ledger_index' => 'current']]
            ]), ['Content-Type: application/json']);
            $json = self::decode($resp);
            if ($json !== null && isset($json['result']['account_data'])) {
                return ['balance' => (float)$json['result']['account_data']['Balance'] / 1000000, 'unconfirmed_balance' => 0, 'address' => $address];
            }
            $resp = self::httpGet("https://api.xrpscan.com/api/v1/account/{$address}");
            $json = self::decode($resp);
            if ($json !== null && isset($json['balance'])) {
                return ['balance' => (float)$json['balance'], 'unconfirmed_balance' => 0, 'address' => $address];
            }
            $xrpFallbacks = [
                "https://xrplcluster2.com",
                "https://s1.ripple.com:51234",
            ];
            foreach ($xrpFallbacks as $xrpUrl) {
                $resp = self::httpPost($xrpUrl, json_encode([
                    'method' => 'account_info',
                    'params' => [['account' => $address, 'ledger_index' => 'current']]
                ]), ['Content-Type: application/json']);
                $json = self::decode($resp);
                if ($json !== null && isset($json['result']['account_data'])) {
                    return ['balance' => (float)$json['result']['account_data']['Balance'] / 1000000, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
            return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
        }

        // BTC/LTC: mempool-style HTTPS APIs are tried below via settings config; Electrum is the last-resort fallback.

        $freeApiChains = [
            'DOGE' => [
                ['url' => "https://doge.blockscout.com/api?module=account&action=balance&address={$address}", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/doge/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/dogecoin/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'BCH' => [
                ['url' => "https://bch.blockscout.com/api?module=account&action=balance&address={$address}", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/bch/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/bitcoin-cash/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'DASH' => [
                ['url' => "https://dash.blockscout.com/api?module=account&action=balance&address={$address}", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/dash/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/dash/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'DGB' => [
                ['url' => "https://digiexplorer.info/api/address/{$address}", 'fmt' => 'digiexplorer'],
                ['url' => "https://api.blockcypher.com/v1/dgb/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/digibyte/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'RVN' => [
                ['url' => "https://ravencoin.network/api/address/{$address}", 'fmt' => 'rvnexplorer'],
                ['url' => "https://api.blockcypher.com/v1/rvn/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/ravencoin/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'ZEC' => [
                ['url' => "https://zcash.blockscout.com/api?module=account&action=balance&address={$address}", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/zec/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/zcash/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'BSV' => [
                ['url' => "https://api.whatsonchain.com/v1/bsv/main/address/{$address}/balance", 'fmt' => 'whatsonchain'],
                ['url' => "https://api.blockcypher.com/v1/bsv/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://blockchair.com/bitcoin-sv/address/{$address}", 'fmt' => 'blockchair_addr'],
            ],
            'BTG' => [
                ['url' => "https://btgexplorer.com/api/address/{$address}", 'fmt' => 'btgexplorer'],
                ['url' => "https://api.blockcypher.com/v1/btg/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
            ],
            'XVG' => [
                ['url' => "https://verge-blockchain.info/api/address/{$address}", 'fmt' => 'vergeexplorer'],
                ['url' => "https://api.blockcypher.com/v1/xvg/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
                ['url' => "https://xvgexplorer.com/api/address/{$address}", 'fmt' => 'xvgexplorer'],
            ],
            'QTUM' => [
                ['url' => "https://qtum.blockscout.com/api?module=account&action=balance&address={$address}", 'fmt' => 'blockscout'],
                ['url' => "https://qtum.info/api/address/{$address}", 'fmt' => 'qtuminfo'],
                ['url' => "https://api.blockcypher.com/v1/qtum/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
            ],
            'VTC' => [
                ['url' => "https://vtcexplorer.com/api/address/{$address}", 'fmt' => 'vtcexplorer'],
                ['url' => "https://explorer.vertcoin.org/api/address/{$address}", 'fmt' => 'vtcexplorer2'],
                ['url' => "https://api.blockcypher.com/v1/vtc/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
            ],
            'KMD' => [
                ['url' => "https://kmdexplorer.io/api/address/{$address}", 'fmt' => 'kmdexplorer'],
                ['url' => "https://kmd.explorer.croswap.com/api/address/{$address}", 'fmt' => 'kmdexplorer2'],
                ['url' => "https://api.blockcypher.com/v1/kmd/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
            ],
            'BTC' => [
                ['url' => "https://mempool.space/api/address/{$address}", 'fmt' => 'mempool'],
                ['url' => "https://blockstream.info/api/address/{$address}", 'fmt' => 'mempool'],
                ['url' => "https://blockchain.info/rawaddr/{$address}?limit=0", 'fmt' => 'blockchaininfo'],
                ['url' => "https://blockchain.info/balance?active={$address}", 'fmt' => 'blockchaininfo2'],
            ],
            'LTC' => [
                ['url' => "https://litecoinspace.org/api/address/{$address}", 'fmt' => 'mempool'],
                ['url' => "https://ltc.bitrefill.com/api/address/{$address}", 'fmt' => 'blockcypher'],
                ['url' => "https://api.blockcypher.com/v1/ltc/main/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
            ],
            'BTCT' => [
                ['url' => "https://mempool.space/testnet/api/address/{$address}", 'fmt' => 'mempool'],
                ['url' => "https://api.blockcypher.com/v1/btc/testnet/addrs/{$address}/balance", 'fmt' => 'blockcypher'],
            ],
        ];

        if (isset($freeApiChains[$coinUpper])) {
            foreach ($freeApiChains[$coinUpper] as $ep) {
                $resp = self::httpGet($ep['url']);
                if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) continue;
                $data = self::decode($resp);
                if ($data === null) continue;
                $bal = null;
                if ($ep['fmt'] === 'blockcypher' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'blockscout' && ($data['status'] ?? '') === '1' && isset($data['result'])) {
                    $bal = (float)$data['result'] / 1e8;
                } elseif ($ep['fmt'] === 'blockchaininfo' && isset($data['final_balance'])) {
                    $bal = (float)$data['final_balance'] / 1e8;
                } elseif ($ep['fmt'] === 'blockchaininfo2' && isset($data[$address])) {
                    $bal = (float)($data[$address]['final_balance'] ?? 0) / 1e8;
                } elseif ($ep['fmt'] === 'mempool' && isset($data['chain_stats'])) {
                    $bal = (float)(($data['chain_stats']['funded_txo_sum'] ?? 0) - ($data['chain_stats']['spent_txo_sum'] ?? 0)) / 1e8;
                } elseif ($ep['fmt'] === 'whatsonchain' && isset($data['confirmed'])) {
                    $bal = (float)$data['confirmed'] / 1e8;
                } elseif ($ep['fmt'] === 'digiexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'rvnexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'btgexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'vergeexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'qtuminfo' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'vtcexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'kmdexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'vtcexplorer2' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'kmdexplorer2' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'xvgexplorer' && isset($data['balance'])) {
                    $bal = (float)$data['balance'] / 1e8;
                } elseif ($ep['fmt'] === 'blockchair_addr' && isset($data['data'])) {
                    $addrData = $data['data'][$address] ?? null;
                    if ($addrData !== null && isset($addrData['address']['balance'])) {
                        $bal = (float)$addrData['address']['balance'] / 1e8;
                    }
                }
                if ($bal !== null) return ['balance' => $bal, 'unconfirmed_balance' => 0, 'address' => $address];
            }
        }

        $blockchairChains = [
            'BTC' => 'bitcoin', 'LTC' => 'litecoin', 'DOGE' => 'dogecoin',
            'BCH' => 'bitcoin-cash', 'DASH' => 'dash', 'DGB' => 'digibyte',
            'RVN' => 'ravencoin', 'BTG' => 'bitcoin-gold', 'ZEC' => 'zcash',
            'BSV' => 'bitcoin-sv', 'XVG' => 'verge', 'KMD' => 'komodo',
            'ETC' => 'ethereum-classic',
        ];
        if (isset($blockchairChains[$coinUpper])) {
            $data = self::getFromBlockchair($blockchairChains[$coinUpper], "address/{$address}");
            if ($data !== null && isset($data['address'])) {
                $addr = $data['address'];
                $bal = (float)($addr['balance'] ?? '0') / 1e8;
                if ($bal > 0 || isset($addr['transaction_count'])) {
                    return ['balance' => $bal, 'unconfirmed_balance' => 0, 'address' => $address];
                }
            }
        }

        $config = self::getSettings()[$coinUpper] ?? null;
        if ($config !== null && !isset($freeApiChains[$coinUpper])) {
            $resp = self::httpGet($config['api'] . '/address/' . $address);
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = self::decode($resp);
                if ($data !== null) {
                    if (isset($data['chain_stats'])) {
                        return [
                            'balance' => self::normalizeBalance($data['chain_stats']['funded_txo_sum'] - $data['chain_stats']['spent_txo_sum'], $coinUpper),
                            'unconfirmed_balance' => self::normalizeBalance(($data['mempool_stats']['funded_txo_sum'] ?? 0) - ($data['mempool_stats']['spent_txo_sum'] ?? 0), $coinUpper),
                            'address' => $address,
                        ];
                    }
                    if (isset($data['balance'])) {
                        return [
                            'balance' => self::normalizeBalance($data['balance'], $coinUpper),
                            'unconfirmed_balance' => self::normalizeBalance($data['unconfirmed_balance'] ?? 0, $coinUpper),
                            'address' => $address,
                        ];
                    }
                }
            }
        }

        if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC'], true) && !isset($freeApiChains[$coinUpper])) {
            try {
                require_once __DIR__ . '/ElectrumClient.php';
                if (!ElectrumClient::isAvailable()) return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
                $electrum = new ElectrumClient($coinUpper);
                $electrum->discoverServers();
                return $electrum->getBalance($address);
            } catch (Throwable $e) {
                error_log("Electrum balance error for {$coinUpper}: " . $e->getMessage());
            }
        }

        return ['balance' => 0, 'unconfirmed_balance' => 0, 'address' => $address];
    }

    public static function getUTXOs($address, $coin = 'BTC') {
        $coinUpper = strtoupper($coin);
        $config = self::getSettings()[$coinUpper] ?? null;
        if ($config === null) return [];

        $resp = self::httpGet($config['api'] . '/address/' . $address . '/utxo');
        if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
            $data = self::decode($resp);
            if (is_array($data)) {
                $utxos = [];
                foreach ($data as $utxo) {
                    $utxos[] = [
                        'txid' => $utxo['txid'] ?? $utxo['tx_hash'] ?? null,
                        'vout' => $utxo['vout'] ?? $utxo['tx_output_n'] ?? null,
                        'value' => $utxo['value'] ?? 0,
                        'status' => ['confirmed' => empty($utxo['spent'])],
                    ];
                }
                return $utxos;
            }
        }

        if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC'], true)) {
            $utxoEndpoints = [];
            if ($coinUpper === 'BTC') {
                $utxoEndpoints[] = "https://blockstream.info/api/address/{$address}/utxo";
                $utxoEndpoints[] = "https://mempool.space/api/address/{$address}/utxo";
            } elseif ($coinUpper === 'BTCT') {
                $utxoEndpoints[] = "https://mempool.space/testnet/api/address/{$address}/utxo";
            } elseif ($coinUpper === 'LTC') {
                $utxoEndpoints[] = "https://blockstream.info/api/address/{$address}/utxo";
                $utxoEndpoints[] = "https://litecoinspace.org/api/address/{$address}/utxo";
            }
            foreach ($utxoEndpoints as $utxoUrl) {
                $resp = self::httpGet($utxoUrl);
                if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                    $data = self::decode($resp);
                    if (is_array($data)) {
                        $utxos = [];
                        foreach ($data as $utxo) {
                            $confirmed = ($utxo['status']['confirmed'] ?? false);
                            $utxos[] = [
                                'txid' => $utxo['txid'] ?? null,
                                'vout' => $utxo['vout'] ?? null,
                                'value' => $utxo['value'] ?? 0,
                                'status' => ['confirmed' => $confirmed],
                            ];
                        }
                        if (count($utxos) > 0) return $utxos;
                    }
                }
            }
        }

        $resp = self::httpGet($config['api'] . '/addrs/' . $address . '?unspent=true');
        if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
            $data = self::decode($resp);
            if ($data !== null && isset($data['txrefs']) && is_array($data['txrefs'])) {
                $utxos = [];
                foreach ($data['txrefs'] as $tx) {
                    if (!empty($tx['spent'])) continue;
                    $utxos[] = [
                        'txid' => $tx['tx_hash'] ?? null,
                        'vout' => $tx['tx_output_n'] ?? null,
                        'value' => $tx['value'] ?? 0,
                        'status' => ['confirmed' => empty($tx['spent'])],
                    ];
                }
                return $utxos;
            }
        }

        return [];
    }

    public static function getTransactions($address, $coin = 'BTC') {
        $coinUpper = strtoupper($coin);

        if ($coinUpper === 'USDT') {
            $usdtContract = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
            $usdtTxEndpoints = [
                "https://api.etherscan.io/api?module=account&action=tokentx&contractaddress={$usdtContract}&address={$address}&sort=desc&limit=100",
                "https://eth.blockscout.com/api?module=account&action=tokentx&contractaddress={$usdtContract}&address={$address}&sort=desc&limit=100",
            ];
            foreach ($usdtTxEndpoints as $usdtTxUrl) {
                $resp = self::httpGet($usdtTxUrl);
                $json = self::decode($resp);
                if ($json !== null && ($json['status'] ?? '') === '1' && is_array($json['result'] ?? null)) {
                    return self::mapErc20Txs($json['result'], $address, 1e6);
                }
            }
            return [];
        }

        if ($coinUpper === 'POLYGON' || $coinUpper === 'BSC') {
            $scan = $coinUpper === 'POLYGON' ? 'api.polygonscan.com' : 'api.bscscan.com';
            $blockscout = $coinUpper === 'POLYGON' ? 'https://polygon.blockscout.com' : 'https://bsc.blockscout.com';
            $evmTxEndpoints = [
                "https://{$scan}/api?module=account&action=txlist&address={$address}&sort=desc&limit=100",
                "{$blockscout}/api?module=account&action=txlist&address={$address}&sort=desc&limit=100",
            ];
            foreach ($evmTxEndpoints as $evmTxUrl) {
                $resp = self::httpGet($evmTxUrl);
                $json = self::decode($resp);
                if ($json !== null && ($json['status'] ?? '') === '1' && is_array($json['result'] ?? null)) {
                    return self::mapEvmTxs($json['result'], $address, 1e18);
                }
            }
            return [];
        }

        if ($coinUpper === 'ETH') {
            $ethTxEndpoints = [
                "https://api.etherscan.io/api?module=account&action=txlist&address={$address}&sort=desc&limit=100",
                "https://eth.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100",
                "https://api.blockchain.com/v3/explorer/addrs/{$address}/txs",
                "https://api.blockcypher.com/v1/eth/main/addrs/{$address}?limit=100",
            ];
            foreach ($ethTxEndpoints as $ethTxUrl) {
                $resp = self::httpGet($ethTxUrl);
                $json = self::decode($resp);
                if ($json === null) continue;
                if (($json['status'] ?? '') === '1' && is_array($json['result'] ?? null)) {
                    return self::mapEvmTxs($json['result'], $address, 1e18);
                }
                if (isset($json['txs']) && is_array($json['txs'])) {
                    $txs = [];
                    foreach ($json['txs'] as $tx) {
                        $dir = strtolower($tx['to'] ?? '') === strtolower($address) ? 1 : -1;
                        $txs[] = [
                            'tx_hash' => $tx['hash'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => isset($tx['time']) ? strtotime($tx['time']) : null,
                            'value' => $dir * ((float)($tx['value'] ?? 0) / 1e18),
                            'fee' => isset($tx['fee']) ? (float)$tx['fee'] / 1e18 : 0,
                        ];
                    }
                    if (count($txs) > 0) return $txs;
                }
            }
            return [];
        }

        if ($coinUpper === 'ETC') {
            $etcTxEndpoints = [
                "https://etc.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100",
                "https://api.blockscout.com/etc/mainnet/api?module=account&action=txlist&address={$address}&sort=desc&limit=100",
                "https://api.blockcypher.com/v1/etc/main/addrs/{$address}?limit=100",
            ];
            foreach ($etcTxEndpoints as $etcTxUrl) {
                $resp = self::httpGet($etcTxUrl);
                $json = self::decode($resp);
                if ($json !== null && ($json['status'] ?? '') === '1' && is_array($json['result'] ?? null)) {
                    return self::mapEvmTxs($json['result'], $address, 1e18);
                }
            }
            return [];
        }

        if ($coinUpper === 'KASPA') {
            $resp = self::httpGet("https://api.kaspa.org/addresses/{$address}/transactions?limit=100");
            $json = self::decode($resp);
            if (is_array($json)) {
                $txs = [];
                foreach ($json as $tx) {
                    $txs[] = [
                        'tx_hash' => $tx['transaction_id'] ?? $tx['txid'] ?? '',
                        'confirmations' => 0,
                        'timestamp' => $tx['block_time'] ?? time(),
                        'value' => abs((float)($tx['value'] ?? '0')) / 1e8,
                        'fee' => 0,
                    ];
                }
                return $txs;
            }
            return [];
        }

        if ($coinUpper === 'XRP') {
            $resp = self::httpPost('https://xrplcluster.com', json_encode([
                'method' => 'account_tx',
                'params' => [['account' => $address, 'limit' => 100, 'ledger_index_min' => -1]]
            ]), ['Content-Type: application/json']);
            $json = self::decode($resp);
            if ($json !== null && isset($json['result']['transactions']) && is_array($json['result']['transactions'])) {
                $addrLower = strtolower($address);
                $txs = [];
                foreach ($json['result']['transactions'] as $tx) {
                    $amt = (float)($tx['tx']['Amount'] ?? 0) / 1000000;
                    $dest = $tx['tx']['Destination'] ?? '';
                    $isSender = $dest !== '' && strtolower($dest) !== $addrLower;
                    $txs[] = [
                        'tx_hash' => $tx['tx']['hash'] ?? '',
                        'confirmations' => (isset($tx['meta']['delivered_amount']) ? 1 : 0),
                        'timestamp' => isset($tx['tx']['date']) ? ((int)$tx['tx']['date'] + 946684800) : time(),
                        'value' => ($isSender ? -1 : 1) * $amt,
                        'fee' => (float)($tx['tx']['Fee'] ?? 0) / 1000000,
                    ];
                }
                return $txs;
            }
            $resp = self::httpGet("https://api.xrpscan.com/api/v1/account/{$address}/transactions?limit=100");
            $json = self::decode($resp);
            if (is_array($json)) {
                $addrLower = strtolower($address);
                $txs = [];
                foreach ($json as $tx) {
                    $dest = $tx['destination'] ?? '';
                    $isSender = $dest !== '' && strtolower($dest) !== $addrLower;
                    $txs[] = [
                        'tx_hash' => $tx['hash'] ?? '',
                        'confirmations' => 0,
                        'timestamp' => $tx['timestamp'] ?? time(),
                        'value' => ($isSender ? -1 : 1) * (float)($tx['amount'] ?? 0),
                        'fee' => (float)($tx['fee'] ?? 0),
                    ];
                }
                return $txs;
            }
            return [];
        }

        $config = self::getSettings()[$coinUpper] ?? null;

        if ($config !== null) {
            $resp = self::httpGet($config['api'] . '/address/' . $address . '/txs');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = self::decode($resp);
                if (is_array($data) && count($data) > 0) {
                    return self::processMempoolAddressTxs($data, $address);
                }
            }

            $resp = self::httpGet($config['api'] . '/addrs/' . $address . '?limit=100');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = self::decode($resp);
                if ($data !== null && isset($data['txs']) && is_array($data['txs'])) {
                    $txs = [];
                    foreach ($data['txs'] as $tx) {
                        $ts = isset($tx['received']) ? strtotime($tx['received']) : ($tx['status']['block_time'] ?? null);
                        $txs[] = [
                            'tx_hash' => $tx['hash'] ?? $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'block_height' => $tx['block_height'] ?? null,
                            'timestamp' => $ts,
                            'value' => ($tx['total'] ?? 0) / 1e8,
                            'fee' => ($tx['fees'] ?? 0) / 1e8,
                        ];
                    }
                    if (count($txs) > 0) return $txs;
                }
            }
        }

        $altTxApis = [
            'BTCT' => [
                ['url' => "https://mempool.space/testnet/api/address/{$address}/txs", 'fmt' => 'mempool'],
                ['url' => "https://api.blockcypher.com/v1/btc/testnet/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'DOGE' => [
                ['url' => "https://doge.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/doge/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'BCH' => [
                ['url' => "https://bch.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/bch/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'DASH' => [
                ['url' => "https://dash.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/dash/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'ZEC' => [
                ['url' => "https://zcash.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/zec/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'BSV' => [
                ['url' => "https://api.whatsonchain.com/v1/bsv/main/address/{$address}/history", 'fmt' => 'whatsonchain'],
                ['url' => "https://api.blockcypher.com/v1/bsv/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'DGB' => [
                ['url' => "https://dgb.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://digiexplorer.info/api/address/{$address}", 'fmt' => 'digiexplorer'],
                ['url' => "https://api.blockcypher.com/v1/dgb/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'RVN' => [
                ['url' => "https://rvn.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://ravencoin.network/api/address/{$address}", 'fmt' => 'rvnexplorer'],
                ['url' => "https://api.blockcypher.com/v1/rvn/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'BTG' => [
                ['url' => "https://btg.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://btgexplorer.com/api/address/{$address}", 'fmt' => 'btgexplorer'],
                ['url' => "https://api.blockcypher.com/v1/btg/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'XVG' => [
                ['url' => "https://api.blockcypher.com/v1/xvg/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
                ['url' => "https://verge-blockchain.info/api/address/{$address}", 'fmt' => 'vergeexplorer'],
                ['url' => "https://xvgexplorer.com/api/address/{$address}", 'fmt' => 'xvgexplorer'],
            ],
            'QTUM' => [
                ['url' => "https://qtum.blockscout.com/api?module=account&action=txlist&address={$address}&sort=desc&limit=100", 'fmt' => 'blockscout'],
                ['url' => "https://api.blockcypher.com/v1/qtum/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
                ['url' => "https://qtum.info/api/address/{$address}", 'fmt' => 'qtuminfo'],
            ],
            'VTC' => [
                ['url' => "https://vtcexplorer.com/api/address/{$address}", 'fmt' => 'vtcexplorer'],
                ['url' => "https://explorer.vertcoin.org/api/address/{$address}", 'fmt' => 'vtcexplorer2'],
                ['url' => "https://api.blockcypher.com/v1/vtc/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'KMD' => [
                ['url' => "https://kmdexplorer.io/api/address/{$address}", 'fmt' => 'kmdexplorer'],
                ['url' => "https://kmd.explorer.croswap.com/api/address/{$address}", 'fmt' => 'kmdexplorer2'],
                ['url' => "https://api.blockcypher.com/v1/kmd/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
            'BTC' => [
                ['url' => "https://blockstream.info/api/address/{$address}/txs", 'fmt' => 'blockstream'],
                ['url' => "https://blockchain.info/rawaddr/{$address}?limit=50", 'fmt' => 'blockchaininfo'],
            ],
            'LTC' => [
                ['url' => "https://litecoinspace.org/api/address/{$address}/txs", 'fmt' => 'mempool'],
                ['url' => "https://api.blockcypher.com/v1/ltc/main/addrs/{$address}?limit=100", 'fmt' => 'bc'],
            ],
        ];

        if (isset($altTxApis[$coinUpper])) {
            foreach ($altTxApis[$coinUpper] as $alt) {
                $resp = self::httpGet($alt['url']);
                if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) continue;
                $data = self::decode($resp);
                if ($data === null) continue;
                $txs = [];
                if ($alt['fmt'] === 'bc' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['hash'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => isset($tx['received']) ? strtotime($tx['received']) : time(),
                            'value' => ($tx['total'] ?? 0) / 1e8,
                            'fee' => ($tx['fees'] ?? 0) / 1e8,
                        ];
                    }
                } elseif ($alt['fmt'] === 'mempool' && is_array($data)) {
                    $txs = self::processMempoolAddressTxs($data, $address);
                } elseif ($alt['fmt'] === 'blockstream' && is_array($data)) {
                    $txs = self::processMempoolAddressTxs($data, $address);
                } elseif ($alt['fmt'] === 'blockscout' && ($data['status'] ?? '') === '1' && is_array($data['result'] ?? null)) {
                    $txs = self::mapEvmTxs($data['result'], $address, 1e8);
                } elseif ($alt['fmt'] === 'blockchaininfo' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['hash'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => ($tx['result'] ?? 0) / 1e8,
                            'fee' => ($tx['fee'] ?? 0) / 1e8,
                        ];
                    }
                } elseif ($alt['fmt'] === 'whatsonchain' && is_array($data)) {
                    foreach ($data as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'digiexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'rvnexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'btgexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'vergeexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'qtuminfo' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'vtcexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'kmdexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'vtcexplorer2' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'kmdexplorer2' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                } elseif ($alt['fmt'] === 'xvgexplorer' && isset($data['txs']) && is_array($data['txs'])) {
                    foreach ($data['txs'] as $tx) {
                        $txs[] = [
                            'tx_hash' => $tx['txid'] ?? '',
                            'confirmations' => $tx['confirmations'] ?? 0,
                            'timestamp' => $tx['time'] ?? null,
                            'value' => (float)($tx['value'] ?? 0) / 1e8,
                            'fee' => 0,
                        ];
                    }
                }
                if (count($txs) > 0) return $txs;
            }
        }

        $blockchairTxChains = [
            'BTC' => 'bitcoin', 'LTC' => 'litecoin', 'DOGE' => 'dogecoin',
            'BCH' => 'bitcoin-cash', 'DASH' => 'dash', 'DGB' => 'digibyte',
            'RVN' => 'ravencoin', 'BTG' => 'bitcoin-gold', 'ZEC' => 'zcash',
            'BSV' => 'bitcoin-sv', 'XVG' => 'verge', 'KMD' => 'komodo',
            'ETC' => 'ethereum-classic',
        ];
        if (isset($blockchairTxChains[$coinUpper])) {
            $data = self::getFromBlockchair($blockchairTxChains[$coinUpper], "address/{$address}");
            if ($data !== null && isset($data['transactions']) && is_array($data['transactions'])) {
                $txs = [];
                foreach ($data['transactions'] as $tx) {
                    $txs[] = [
                        'tx_hash' => $tx['hash'] ?? '',
                        'confirmations' => 0,
                        'timestamp' => isset($tx['time']) ? strtotime($tx['time']) : null,
                        'value' => (float)($tx['output_total'] ?? 0) / 1e8,
                        'fee' => (float)($tx['fee'] ?? 0) / 1e8,
                    ];
                }
                if (count($txs) > 0) return $txs;
            }
        }

        if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC'], true)) {
            try {
                require_once __DIR__ . '/ElectrumClient.php';
                if (!ElectrumClient::isAvailable()) return [];
                $electrum = new ElectrumClient($coinUpper);
                $electrum->discoverServers();
                return $electrum->getTransactions($address);
            } catch (Throwable $e) {
                error_log("Electrum tx error for {$coinUpper}: " . $e->getMessage());
            }
        }

        return [];
    }

    private static function mapEvmTxs($result, $address, $divisor) {
        $addrLower = strtolower($address);
        $txs = [];
        foreach ($result as $tx) {
            $to = strtolower($tx['to'] ?? '');
            $dir = ($to === $addrLower) ? 1 : -1;
            $txs[] = [
                'tx_hash' => $tx['hash'] ?? '',
                'confirmations' => (int)($tx['confirmations'] ?? 0),
                'timestamp' => $tx['timeStamp'] ?? null,
                'value' => $dir * ((float)($tx['value'] ?? 0) / $divisor),
                'fee' => ((float)($tx['gasUsed'] ?? 0) * (float)($tx['gasPrice'] ?? 0)) / 1e18,
            ];
        }
        return $txs;
    }

    private static function mapErc20Txs($result, $address, $divisor) {
        $addrLower = strtolower($address);
        $txs = [];
        foreach ($result as $tx) {
            $to = strtolower($tx['to'] ?? '');
            $dir = ($to === $addrLower) ? 1 : -1;
            $txs[] = [
                'tx_hash' => $tx['hash'] ?? '',
                'confirmations' => $tx['confirmations'] ?? 0,
                'timestamp' => $tx['timeStamp'] ?? null,
                'value' => $dir * ((float)($tx['value'] ?? 0) / $divisor),
                'fee' => ((float)($tx['gasUsed'] ?? 0) * (float)($tx['gasPrice'] ?? 0)) / 1e18,
            ];
        }
        return $txs;
    }

    private static function extractAddresses($tx, $address) {
        $isSender = false;
        if (isset($tx['vin']) && is_array($tx['vin'])) {
            foreach ($tx['vin'] as $vin) {
                $a = $vin['prevout']['scriptpubkey_address'] ?? ($vin['addresses'][0] ?? null);
                if ($a === $address) { $isSender = true; break; }
            }
        }
        $fromAddress = null;
        $toAddress = null;
        if ($isSender) {
            $fromAddress = $address;
            if (isset($tx['vout']) && is_array($tx['vout'])) {
                foreach ($tx['vout'] as $vout) {
                    $a = $vout['scriptpubkey_address'] ?? ($vout['scriptPubKey']['addresses'][0] ?? null);
                    if ($a !== null && $a !== $address) { $toAddress = $a; break; }
                }
            }
        } elseif (isset($tx['vin']) && is_array($tx['vin'])) {
            foreach ($tx['vin'] as $vin) {
                $a = $vin['prevout']['scriptpubkey_address'] ?? ($vin['addresses'][0] ?? null);
                if ($a !== null && $a !== $address) { $fromAddress = $a; break; }
            }
            $toAddress = $address;
        }
        return ['from_address' => $fromAddress, 'to_address' => $toAddress];
    }

    private static function calculateTxValue($tx, $address) {
        if (!isset($tx['vout']) || !is_array($tx['vout'])) return 0;
        $isSender = false;
        if (isset($tx['vin']) && is_array($tx['vin'])) {
            foreach ($tx['vin'] as $vin) {
                $a = $vin['prevout']['scriptpubkey_address'] ?? ($vin['addresses'][0] ?? null);
                if ($a === $address) { $isSender = true; break; }
            }
        }
        $received = 0;
        foreach ($tx['vout'] as $vout) {
            $addr = $vout['scriptpubkey_address'] ?? ($vout['scriptPubKey']['addresses'][0] ?? null);
            if ($addr === $address) {
                $received += $vout['value'] ?? 0;
            }
        }
        if ($isSender) {
            $spent = 0;
            foreach ($tx['vin'] as $vin) {
                $a = $vin['prevout']['scriptpubkey_address'] ?? ($vin['addresses'][0] ?? null);
                if ($a === $address) {
                    $spent += $vin['prevout']['value'] ?? 0;
                }
            }
            return $received - $spent;
        }
        return $received;
    }

    private static function processMempoolAddressTxs($txs, $address) {
        $result = [];
        foreach ($txs as $tx) {
            $addrInfo = self::extractAddresses($tx, $address);
            $result[] = [
                'tx_hash' => $tx['txid'] ?? $tx['hash'] ?? '',
                'confirmations' => 0,
                'block_height' => ($tx['status']['confirmed'] ?? false) ? ($tx['status']['block_height'] ?? null) : null,
                'timestamp' => $tx['status']['block_time'] ?? null,
                'value' => self::calculateTxValue($tx, $address) / 1e8,
                'fee' => ($tx['fee'] ?? 0) / 1e8,
                'from_address' => $addrInfo['from_address'],
                'to_address' => $addrInfo['to_address'],
            ];
        }
        return $result;
    }

    public static function broadcastTx($rawTxHex, $coin = 'BTC') {
        $upper = strtoupper($coin);

        $epMap = [
            'BTC' => [
                ['https://blockstream.info/api/tx', false],
                ['https://mempool.space/api/tx', false],
                ['https://api.blockcypher.com/v1/btc/main/txs/push', true],
                ['https://blockchain.info/pushtx', 'blockchaininfo'],
            ],
            'BTCT' => [
                ['https://mempool.space/testnet/api/tx', false],
                ['https://api.blockcypher.com/v1/btc/testnet/txs/push', true],
            ],
            'LTC' => [
                ['https://litecoinspace.org/api/tx', false],
                ['https://api.blockcypher.com/v1/ltc/main/txs/push', true],
            ],
            'DOGE' => [
                ['https://api.blockcypher.com/v1/doge/main/txs/push', true],
            ],
            'BCH' => [
                ['https://api.blockcypher.com/v1/bch/main/txs/push', true],
            ],
            'DASH' => [
                ['https://api.blockcypher.com/v1/dash/main/txs/push', true],
            ],
            'DGB' => [
                ['https://api.blockcypher.com/v1/dgb/main/txs/push', true],
                ['https://digiexplorer.info/api/tx/send', 'digiexplorer'],
            ],
            'BTG' => [
                ['https://api.blockcypher.com/v1/btg/main/txs/push', true],
                ['https://btgexplorer.com/api/tx/send', 'btgexplorer'],
            ],
            'RVN' => [
                ['https://api.blockcypher.com/v1/rvn/main/txs/push', true],
                ['https://ravencoin.network/api/tx/send', 'rvnexplorer'],
            ],
            'ZEC' => [
                ['https://api.blockcypher.com/v1/zec/main/txs/push', true],
            ],
            'BSV' => [
                ['https://api.blockcypher.com/v1/bsv/main/txs/push', true],
                ['https://api.whatsonchain.com/v1/bsv/main/tx/raw', 'whatsonchain'],
            ],
            'XVG' => [
                ['https://api.blockcypher.com/v1/xvg/main/txs/push', true],
                ['https://xvgexplorer.com/api/tx/send', 'xvgexplorer'],
            ],
            'QTUM' => [
                ['https://api.blockcypher.com/v1/qtum/main/txs/push', true],
                ['https://qtum.info/api/tx/send', 'qtuminfo'],
            ],
            'VTC' => [
                ['https://api.blockcypher.com/v1/vtc/main/txs/push', true],
                ['https://vtcexplorer.com/api/tx/send', 'vtcexplorer'],
                ['https://explorer.vertcoin.org/api/tx/send', 'vtcexplorer2'],
            ],
            'KMD' => [
                ['https://api.blockcypher.com/v1/kmd/main/txs/push', true],
                ['https://kmdexplorer.io/api/tx/send', 'kmdexplorer'],
            ],
            'KASPA' => [['https://api.kaspa.org/transactions', false]],
            'ETH' => [
                ['https://eth.blockscout.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
                ['https://api.etherscan.io/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
                ['https://api.blockchain.com/v3/explorer/tx/push', 'blockchaininfo'],
            ],
            'ETC' => [
                ['https://etc.blockscout.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
            ],
            'USDT' => [
                ['https://eth.blockscout.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
                ['https://api.etherscan.io/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
            ],
            'POLYGON' => [
                ['https://polygon.blockscout.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
                ['https://api.polygonscan.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
            ],
            'BSC' => [
                ['https://bsc.blockscout.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
                ['https://api.bscscan.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x', 'eth'],
            ],
            'XRP' => [
                ['https://xrplcluster.com', 'xrp'],
                ['https://api.xrpscan.com/api/v1/tx/submit', 'xrp2'],
                ['https://xrplcluster2.com', 'xrp'],
            ],
        ];

        $eps = $epMap[$upper] ?? [];
        foreach ($eps as $ep) {
            $url = $ep[0];
            $fmt = $ep[1];
            try {
                if ($fmt === 'xrp') {
                    $resp = self::httpPost($url, json_encode([
                        'method' => 'submit',
                        'params' => [['tx_blob' => $rawTxHex]],
                    ]), ['Content-Type: application/json']);
                } elseif ($fmt === 'xrp2') {
                    $resp = self::httpPost($url, json_encode(['tx_blob' => $rawTxHex]), ['Content-Type: application/json']);
                } elseif ($fmt === 'eth') {
                    $resp = self::httpGet($url . $rawTxHex);
                } elseif ($fmt === 'blockchaininfo') {
                    $resp = self::httpPost($url, "tx=" . $rawTxHex, ['Content-Type: application/x-www-form-urlencoded']);
                } elseif ($fmt === 'whatsonchain') {
                    $resp = self::httpPost($url, json_encode(['hex' => $rawTxHex]), ['Content-Type: application/json']);
                } elseif ($fmt === 'digiexplorer' || $fmt === 'rvnexplorer' || $fmt === 'qtuminfo' || $fmt === 'btgexplorer' || $fmt === 'vtcexplorer' || $fmt === 'vtcexplorer2' || $fmt === 'xvgexplorer' || $fmt === 'kmdexplorer') {
                    $resp = self::httpPost($url, json_encode(['hex' => $rawTxHex]), ['Content-Type: application/json']);
                } elseif ($fmt === true) {
                    $resp = self::httpPost($url, json_encode(['tx' => $rawTxHex]), ['Content-Type: application/json']);
                } else {
                    $resp = self::httpPost($url, $rawTxHex, ['Content-Type: text/plain']);
                }
                if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) continue;
                $respText = trim($resp['body']);
                $txid = $respText;
                $json = json_decode($respText, true);
                if (is_array($json)) {
                    $txid = $json['tx']['hash'] ?? $json['hash'] ?? $json['txid'] ?? $json['data']['txid'] ?? $json['result']['tx_json']['hash'] ?? $json['result'] ?? $respText;
                }
                if (is_array($txid)) $txid = $respText;
                if (is_string($txid)) $txid = preg_replace('/^0x/', '', $txid);
                if (is_string($txid) && preg_match('/^[0-9a-fA-F]{64}$/', $txid)) {
                    return ['tx' => ['hash' => $txid], 'confirmed' => false];
                }
            } catch (Throwable $e) {
                error_log("Broadcast endpoint failed for {$coin}: {$url} - " . $e->getMessage());
            }
        }

        if (in_array($upper, ['BTC', 'BTCT', 'LTC'], true)) {
            try {
                require_once __DIR__ . '/ElectrumClient.php';
                if (!ElectrumClient::isAvailable()) throw new Exception('Broadcast failed - all methods exhausted');
                $electrum = new ElectrumClient($upper);
                $electrum->discoverServers();
                return $electrum->broadcast($rawTxHex);
            } catch (Throwable $e) {
                error_log("Electrum broadcast error for {$upper}: " . $e->getMessage());
            }
        }

        throw new Exception('Broadcast failed - all methods exhausted');
    }

    public static function getFeeEstimation($coin = 'BTC') {
        $coinUpper = strtoupper($coin);
        $config = self::getSettings()[$coinUpper] ?? null;
        if ($config !== null && isset($config['api'])) {
            $resp = self::httpGet($config['api'] . '/v1/fees/recommended');
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = self::decode($resp);
                if ($data !== null && isset($data['fastestFee'])) {
                    return [
                        'slow' => $data['hourFee'] ?? 10,
                        'medium' => $data['halfHourFee'] ?? $data['fastestFee'] ?? 20,
                        'fast' => $data['fastestFee'] ?? 50,
                    ];
                }
            }
        }

        $blockcypherFeeCoins = ['DOGE', 'BCH', 'DASH', 'DGB', 'RVN', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'];
        if (in_array($coinUpper, $blockcypherFeeCoins)) {
            $coinLower = strtolower($coinUpper);
            $resp = self::httpGet("https://api.blockcypher.com/v1/{$coinLower}/main");
            if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                $data = self::decode($resp);
                if ($data !== null && isset($data['medium_fee_per_byte'])) {
                    return [
                        'slow' => max(1, (float)($data['low_fee_per_byte'] ?? 1)),
                        'medium' => max(1, (float)($data['medium_fee_per_byte'] ?? 3)),
                        'fast' => max(1, (float)($data['high_fee_per_byte'] ?? 10)),
                    ];
                }
            }
        }

        $evmFeeEndpoints = [
            'ETH' => [
                'https://eth.blockscout.com/api?module=proxy&action=eth_gasPrice',
                'https://api.etherscan.io/api?module=proxy&action=eth_gasPrice',
            ],
            'ETC' => [
                'https://etc.blockscout.com/api?module=proxy&action=eth_gasPrice',
                'https://api.blockscout.com/etc/mainnet/api?module=proxy&action=eth_gasPrice',
            ],
            'POLYGON' => [
                'https://polygon.blockscout.com/api?module=proxy&action=eth_gasPrice',
                'https://api.polygonscan.com/api?module=proxy&action=eth_gasPrice',
            ],
            'BSC' => [
                'https://bsc.blockscout.com/api?module=proxy&action=eth_gasPrice',
                'https://api.bscscan.com/api?module=proxy&action=eth_gasPrice',
            ],
            'USDT' => [
                'https://eth.blockscout.com/api?module=proxy&action=eth_gasPrice',
                'https://api.etherscan.io/api?module=proxy&action=eth_gasPrice',
            ],
        ];
        if (isset($evmFeeEndpoints[$coinUpper])) {
            foreach ($evmFeeEndpoints[$coinUpper] as $feeUrl) {
                $resp = self::httpGet($feeUrl);
                if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) continue;
                $data = self::decode($resp);
                if ($data === null) continue;
                $hex = $data['result'] ?? null;
                if (!is_string($hex) || !preg_match('/^0x[0-9a-fA-F]+$/', $hex)) continue;
                $gasGwei = hexdec($hex) / 1e9;
                if ($gasGwei <= 0) continue;
                $divisor = 1e9;
                $txSize = $coinUpper === 'USDT' ? 100000 : 21000;
                return [
                    'slow' => $gasGwei * $txSize / $divisor,
                    'medium' => $gasGwei * $txSize / $divisor,
                    'fast' => $gasGwei * $txSize / $divisor,
                ];
            }
        }

        $defaults = [
            'BTC' => ['slow' => 10, 'medium' => 20, 'fast' => 50],
            'BTCT' => ['slow' => 10, 'medium' => 20, 'fast' => 50],
            'LTC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'DOGE' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'BCH' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'RVN' => ['slow' => 1, 'medium' => 2, 'fast' => 8],
            'DASH' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'DGB' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'BTG' => ['slow' => 1, 'medium' => 2, 'fast' => 10],
            'ETC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'USDT' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'POLYGON' => ['slow' => 30, 'medium' => 60, 'fast' => 120],
            'BSC' => ['slow' => 3, 'medium' => 5, 'fast' => 10],
            'ETH' => ['slow' => 10, 'medium' => 20, 'fast' => 50],
            'ZEC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'BSV' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'XVG' => ['slow' => 1, 'medium' => 2, 'fast' => 5],
            'QTUM' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'VTC' => ['slow' => 1, 'medium' => 3, 'fast' => 10],
            'KMD' => ['slow' => 1, 'medium' => 2, 'fast' => 8],
            'KASPA' => ['slow' => 1, 'medium' => 2, 'fast' => 5],
            'XRP' => ['slow' => 0.00001, 'medium' => 0.000012, 'fast' => 0.000015],
        ];

        return $defaults[$coinUpper] ?? ['slow' => 1, 'medium' => 2, 'fast' => 5];
    }
}
