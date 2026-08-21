<?php

class TransactionBuilder {
    private static $evmCoins = [
        'ETH' => ['coin' => 'ETH', 'coinPath' => "m/44'/60'/0'/0/", 'chainId' => 1],
        'POLYGON' => ['coin' => 'POLYGON', 'coinPath' => "m/44'/60'/0'/0/", 'chainId' => 137],
        'BSC' => ['coin' => 'BSC', 'coinPath' => "m/44'/60'/0'/0/", 'chainId' => 56],
        'ETC' => ['coin' => 'ETC', 'coinPath' => "m/44'/61'/0'/0/", 'chainId' => 61],
    ];

    private static $coinTypes = [
        'BTC' => 0, 'BTCT' => 1, 'LTC' => 2, 'DOGE' => 3, 'DASH' => 5,
        'DGB' => 20, 'RVN' => 175, 'BTG' => 156, 'BCH' => 145, 'ETH' => 60, 'ETC' => 61,
        'ZEC' => 133, 'BSV' => 236, 'XVG' => 77, 'QTUM' => 2301, 'VTC' => 28, 'KMD' => 141,
        'XRP' => 144,
    ];

    private static $purposeMap = ['P2PKH' => 44, 'P2SH' => 49, 'P2WPKH' => 84];

    private static $customNetworks = [
        'BTC' => ['bech32' => 'bc', 'pubKeyHash' => 0x00, 'scriptHash' => 0x05, 'wif' => 0x80],
        'BTCT' => ['bech32' => 'tb', 'pubKeyHash' => 0x6f, 'scriptHash' => 0xc4, 'wif' => 0xef],
        'LTC' => ['bech32' => 'ltc', 'pubKeyHash' => 0x30, 'scriptHash' => 0x32, 'wif' => 0xb0],
        'DOGE' => ['bech32' => 'doge', 'pubKeyHash' => 0x1e, 'scriptHash' => 0x16, 'wif' => 0x9e],
        'DASH' => ['bech32' => 'dash', 'pubKeyHash' => 0x4c, 'scriptHash' => 0x10, 'wif' => 0xcc],
        'DGB' => ['bech32' => 'dgb', 'pubKeyHash' => 0x1e, 'scriptHash' => 0x3f, 'wif' => 0x9e],
        'RVN' => ['bech32' => 'rc', 'pubKeyHash' => 0x3c, 'scriptHash' => 0x7a, 'wif' => 0x80],
        'BTG' => ['bech32' => 'btg', 'pubKeyHash' => 0x26, 'scriptHash' => 0x17, 'wif' => 0x80],
        'BCH' => ['bech32' => 'bitcoincash', 'pubKeyHash' => 0x00, 'scriptHash' => 0x05, 'wif' => 0x80],
        'ZEC' => ['bech32' => 'zc', 'pubKeyHash' => 0x1c, 'scriptHash' => 0x1c, 'wif' => 0x80],
        'BSV' => ['bech32' => 'bsv', 'pubKeyHash' => 0x00, 'scriptHash' => 0x05, 'wif' => 0x80],
        'XVG' => ['bech32' => 'xvg', 'pubKeyHash' => 0x1e, 'scriptHash' => 0x21, 'wif' => 0x9e],
        'QTUM' => ['bech32' => 'qc', 'pubKeyHash' => 0x3a, 'scriptHash' => 0x32, 'wif' => 0x80],
        'VTC' => ['bech32' => 'vtc', 'pubKeyHash' => 0x47, 'scriptHash' => 0x05, 'wif' => 0x80],
        'KMD' => ['bech32' => 'kmd', 'pubKeyHash' => 0x3c, 'scriptHash' => 0x3c, 'wif' => 0x80],
    ];

    private static $tatumCoins = [
        'BTC' => 'bitcoin', 'BTCT' => 'bitcoin-testnet', 'LTC' => 'litecoin', 'DOGE' => 'dogecoin', 'BCH' => 'bitcoin-cash',
        'DASH' => 'dash', 'DGB' => 'digibyte', 'RVN' => 'ravencoin', 'BTG' => 'bitcoin-gold', 'ETH' => 'ethereum', 'ETC' => 'ethereum-classic',
        'ZEC' => 'zcash', 'BSV' => 'bitcoin-sv',
    ];

    private static $cryptoapisCoins = [
        'BTC' => 'bitcoin', 'BTCT' => 'bitcoin', 'LTC' => 'litecoin', 'DOGE' => 'dogecoin', 'BCH' => 'bitcoin-cash',
        'DASH' => 'dash', 'DGB' => 'digibyte', 'RVN' => 'ravencoin', 'BTG' => 'bitcoin-gold',
        'ZEC' => 'zcash', 'BSV' => 'bitcoin-sv', 'XVG' => 'verge',
        'QTUM' => 'qtum', 'VTC' => 'vertcoin', 'KMD' => 'komodo',
    ];

    public static function build($params) {
        $coin = strtoupper((string) ($params['coin'] ?? 'XRP'));
        if ($coin === 'XRP') {
            return self::buildXrp($params);
        }
        if (isset(self::$evmCoins[$coin])) {
            return self::buildEvm($params, self::$evmCoins[$coin]);
        }
        throw new Exception('Unsupported coin for transaction building: ' . $coin);
    }

    // XRP (Payment)

    private static function buildXrp($params) {
        $mnemonic = $params['mnemonic'];
        $to = $params['to'];
        $accountIndex = (int) ($params['accountIndex'] ?? 0);
        $amountDrops = self::decimalToBaseUnits($params['amount'], 0);
        $feeDrops = self::decimalToBaseUnits($params['fee'] ?? '12', 0);
        $sequence = (int) ($params['sequence'] ?? 1);
        $lastLedgerSequence = (int) ($params['lastLedgerSequence'] ?? ($sequence + 20));
        $destinationTag = isset($params['destinationTag']) ? (int) $params['destinationTag'] : null;

        if (!empty($params['privKeyHex'])) {
            $privKeyHex = $params['privKeyHex'];
        } else {
            $seed = Bip39::mnemonicToSeed($mnemonic);
            $node = Bip32::derivePath("m/44'/144'/0'/0/" . $accountIndex, $seed);
            $privKeyHex = $node->getPrivateKey();
        }
        $pubKeyHex = strtoupper(Secp256k1::privateKeyToPublicKey($privKeyHex));
        $accountId = self::xrpAccountId(Address::xrpAddress($pubKeyHex));
        $destinationId = self::xrpAccountId($to);

        $fields = [
            self::xrpUint16(2, 0),
            self::xrpUint32(4, $sequence),
        ];
        if ($destinationTag !== null) {
            $fields[] = self::xrpUint32(14, $destinationTag);
        }
        $fields[] = self::xrpUint32(27, $lastLedgerSequence);
        $fields[] = self::xrpAmount(1, $amountDrops);
        $fields[] = self::xrpAmount(8, $feeDrops);
        $fields[] = self::xrpBlob(3, hex2bin($pubKeyHex));
        $fields[] = self::xrpAccount(1, $accountId);
        $fields[] = self::xrpAccount(3, $destinationId);

        usort($fields, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return $a['field'] <=> $b['field'];
            }
            return $a['type'] <=> $b['type'];
        });

        $signingBody = self::serializeXrpFields($fields);
        $signingData = '53545800' . $signingBody;
        $hash = Secp256k1::sha512Half(hex2bin($signingData));
        $sig = Secp256k1::ecdsaSign($hash, hex2bin($privKeyHex));
        $der = self::derEncode($sig['r'], $sig['s']);

        $fields[] = self::xrpBlob(4, $der);
        usort($fields, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return $a['field'] <=> $b['field'];
            }
            return $a['type'] <=> $b['type'];
        });

        $encoded = self::serializeXrpFields($fields);
        $rawTxHex = '0x' . strtoupper('54584E00' . $encoded);
        $txid = bin2hex(Secp256k1::sha512Half(hex2bin(strtolower('54584E00' . $encoded))));

        return [
            'rawTxHex' => $rawTxHex,
            'txid' => $txid,
            'from' => Address::xrpAddress($pubKeyHex),
            'to' => $to,
            'amount' => self::baseUnitsToDecimal($amountDrops, 6),
            'fee' => (string) $feeDrops,
            'coin' => 'XRP',
        ];
    }

    private static function xrpUint16($field, $value) {
        return ['type' => 1, 'field' => $field, 'value' => str_pad(gmp_strval($value, 16), 4, '0', STR_PAD_LEFT)];
    }

    private static function xrpUint32($field, $value) {
        return ['type' => 2, 'field' => $field, 'value' => str_pad(gmp_strval($value, 16), 8, '0', STR_PAD_LEFT)];
    }

    private static function xrpAmount($field, $value) {
        $bytes = str_pad(gmp_export($value), 8, "\x00", STR_PAD_LEFT);
        if (strlen($bytes) > 8) {
            throw new Exception('Amount out of range');
        }
        $bytes[0] = chr(ord($bytes[0]) | 0x40);
        return ['type' => 6, 'field' => $field, 'value' => bin2hex($bytes)];
    }

    private static function xrpBlob($field, $bytes) {
        return ['type' => 7, 'field' => $field, 'value' => bin2hex(chr(strlen($bytes)) . $bytes)];
    }

    private static function xrpAccount($field, $bytes) {
        return ['type' => 8, 'field' => $field, 'value' => bin2hex(chr(strlen($bytes)) . $bytes)];
    }

    private static function serializeXrpFields($fields) {
        $out = '';
        foreach ($fields as $f) {
            if ($f['field'] < 16) {
                $out .= sprintf('%02X', ($f['type'] << 4) | $f['field']);
            } else {
                $out .= sprintf('%02X%02X', ($f['type'] << 4) | 0xF, $f['field']);
            }
            $out .= $f['value'];
        }
        return $out;
    }

    private static function xrpAccountId($address) {
        $decoded = Base58::decode($address, Address::XRP_ALPHABET);
        if (strlen($decoded) < 5) {
            throw new Exception('Invalid XRP address');
        }
        $payload = substr($decoded, 0, -4);
        $checksum = substr($decoded, -4);
        $computed = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        if (!hash_equals($checksum, $computed)) {
            throw new Exception('Invalid XRP address checksum');
        }
        return substr($payload, 1);
    }

    private static function derEncode($r, $s) {
        $rBytes = self::minimalBytes($r);
        $sBytes = self::minimalBytes($s);
        $rBytes = (ord($rBytes[0]) & 0x80) ? "\x00" . $rBytes : $rBytes;
        $sBytes = (ord($sBytes[0]) & 0x80) ? "\x00" . $sBytes : $sBytes;
        $seq = "\x02" . chr(strlen($rBytes)) . $rBytes . "\x02" . chr(strlen($sBytes)) . $sBytes;
        return "\x30" . chr(strlen($seq)) . $seq;
    }

    // EVM (EIP-155 legacy)

    private static function buildEvm($params, $cfg) {
        $mnemonic = $params['mnemonic'];
        $to = self::normalizeEvmAddress($params['to']);
        $accountIndex = (int) ($params['accountIndex'] ?? 0);
        $chainId = (int) ($params['chainId'] ?? $cfg['chainId']);
        $nonce = (int) ($params['nonce'] ?? 0);
        $gasLimit = (int) ($params['gasLimit'] ?? 21000);

        $value = isset($params['amountWei'])
            ? self::decimalToBaseUnits($params['amountWei'], 0)
            : self::decimalToBaseUnits($params['amount'], 18);
        $gasPrice = isset($params['gasPriceWei'])
            ? self::decimalToBaseUnits($params['gasPriceWei'], 0)
            : self::decimalToBaseUnits($params['fee'] ?? '20', 9);
        $data = isset($params['data']) ? hex2bin(preg_replace('/^0x/i', '', $params['data'])) : '';

        if (!empty($params['privKeyHex'])) {
            $privKeyHex = $params['privKeyHex'];
        } else {
            $seed = Bip39::mnemonicToSeed($mnemonic);
            $node = Bip32::derivePath($cfg['coinPath'] . $accountIndex, $seed);
            $privKeyHex = $node->getPrivateKey();
        }

        $items = [
            self::rlpInt($nonce),
            self::rlpInt($gasPrice),
            self::rlpInt($gasLimit),
            self::rlpItem($to),
            self::rlpInt($value),
            self::rlpItem($data),
            self::rlpInt($chainId),
            self::rlpItem(''),
            self::rlpItem(''),
        ];
        $preimage = self::rlpList($items);
        $hash = Keccak::hash($preimage, 256);
        $sig = Secp256k1::ecdsaSign($hash, hex2bin($privKeyHex));
        $v = $chainId * 2 + 35 + $sig['recid'];

        $finalItems = [
            self::rlpInt($nonce),
            self::rlpInt($gasPrice),
            self::rlpInt($gasLimit),
            self::rlpItem($to),
            self::rlpInt($value),
            self::rlpItem($data),
            self::rlpInt($v),
            self::rlpItem(self::minimalBytes($sig['r'])),
            self::rlpItem(self::minimalBytes($sig['s'])),
        ];
        $final = self::rlpList($finalItems);

        return [
            'rawTxHex' => '0x' . bin2hex($final),
            'txid' => '0x' . bin2hex(Keccak::hash($final, 256)),
            'from' => Address::privateKeyToEthereumAddress($privKeyHex),
            'to' => '0x' . bin2hex($to),
            'amount' => self::baseUnitsToDecimal($value, 18),
            'fee' => self::baseUnitsToDecimal($gasPrice, 9),
            'coin' => $cfg['coin'],
        ];
    }

    private static function normalizeEvmAddress($address) {
        $hex = preg_replace('/^0x/i', '', $address);
        if (strlen($hex) !== 40 || !ctype_xdigit($hex)) {
            throw new Exception('Invalid EVM address');
        }
        return hex2bin(strtolower($hex));
    }

    public static function rlpItem($bytes) {
        $len = strlen($bytes);
        if ($len === 1 && ord($bytes) < 0x80) {
            return $bytes;
        }
        if ($len <= 55) {
            return chr(0x80 + $len) . $bytes;
        }
        $lenBytes = self::minimalBytes($len);
        return chr(0xb7 + strlen($lenBytes)) . $lenBytes . $bytes;
    }

    public static function rlpList($items) {
        $body = implode('', $items);
        $len = strlen($body);
        if ($len <= 55) {
            return chr(0xc0 + $len) . $body;
        }
        $lenBytes = self::minimalBytes($len);
        return chr(0xf7 + strlen($lenBytes)) . $lenBytes . $body;
    }

    public static function rlpInt($n) {
        return self::rlpItem(self::minimalBytes($n));
    }

    private static function minimalBytes($n) {
        if (gmp_cmp($n, 0) === 0) {
            return '';
        }
        $hex = gmp_strval($n, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        return hex2bin($hex);
    }

    // Decimal helpers

    private static function decimalToBaseUnits($value, $decimals) {
        $value = (string) $value;
        $neg = strpos($value, '-') === 0;
        if ($neg) {
            $value = substr($value, 1);
        }
        $parts = explode('.', $value);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = isset($parts[1]) ? $parts[1] : '';
        if (strlen($frac) > $decimals) {
            throw new Exception('Too many decimal places');
        }
        $frac = str_pad($frac, $decimals, '0', STR_PAD_RIGHT);
        if ($frac === '') {
            $frac = '0';
        }
        $result = gmp_add(gmp_mul($whole, gmp_pow(10, $decimals)), $frac);
        if ($neg) {
            $result = gmp_neg($result);
        }
        return $result;
    }

    private static function baseUnitsToDecimal($units, $decimals) {
        $neg = gmp_cmp($units, 0) < 0;
        $abs = $neg ? gmp_abs($units) : $units;
        $divisor = gmp_pow(10, $decimals);
        $whole = gmp_div_q($abs, $divisor);
        $frac = gmp_strval(gmp_mod($abs, $divisor));
        if ($decimals > 0) {
            $frac = str_pad($frac, $decimals, '0', STR_PAD_LEFT);
            $frac = rtrim($frac, '0');
            $result = gmp_strval($whole) . ($frac !== '' ? '.' . $frac : '');
        } else {
            $result = gmp_strval($whole);
        }
        return $neg ? '-' . $result : $result;
    }

    public static function getCoinConfig($coin) {
        $upper = strtoupper((string) $coin);
        $configs = [
            'BTC' => ['name' => 'Bitcoin', 'decimals' => 8],
            'BTCT' => ['name' => 'Bitcoin Testnet', 'decimals' => 8],
            'LTC' => ['name' => 'Litecoin', 'decimals' => 8],
            'DOGE' => ['name' => 'Dogecoin', 'decimals' => 8],
            'BCH' => ['name' => 'Bitcoin Cash', 'decimals' => 8],
            'DASH' => ['name' => 'Dash', 'decimals' => 8],
            'DGB' => ['name' => 'DigiByte', 'decimals' => 8],
            'RVN' => ['name' => 'Ravencoin', 'decimals' => 8],
            'BTG' => ['name' => 'Bitcoin Gold', 'decimals' => 8],
            'ETH' => ['name' => 'Ethereum', 'decimals' => 18],
            'ETC' => ['name' => 'Ethereum Classic', 'decimals' => 18],
            'USDT' => ['name' => 'Tether USD', 'decimals' => 6],
            'POLYGON' => ['name' => 'Polygon', 'decimals' => 18],
            'BSC' => ['name' => 'Binance Smart Chain', 'decimals' => 18],
            'ZEC' => ['name' => 'Zcash', 'decimals' => 8],
            'BSV' => ['name' => 'Bitcoin SV', 'decimals' => 8],
            'XVG' => ['name' => 'Verge', 'decimals' => 8],
            'QTUM' => ['name' => 'Qtum', 'decimals' => 8],
            'VTC' => ['name' => 'Vertcoin', 'decimals' => 8],
            'KMD' => ['name' => 'Komodo', 'decimals' => 8],
        ];
        return $configs[$upper] ?? $configs['BTC'];
    }

    private static function resolveSigningKey($wallet, $password) {
        // Non-custodial build: no keys are stored server-side, so the server
        // cannot resolve a signing key. Signing must happen client-side; the
        // public build*() methods below still accept an explicit key for
        // integrations that sign locally.
        throw new Exception('Server-side signing is not available in the non-custodial edition');
        // (unreachable)
    }

    public static function buildAndSignTransaction($walletId, $password, $toAddress, $amount, $fee, $coin, $fromAddress, $addressType, $extraId) {
        $upper = strtoupper((string) $coin);
        $wallet = Database::query('SELECT * FROM wallets WHERE id = ?', [$walletId]);
        if (count($wallet) === 0) {
            throw new Exception('Wallet not found');
        }
        $signing = self::resolveSigningKey($wallet[0], $password);
        $privKeyHex = $signing['type'] === 'private' ? $signing['privKeyHex'] : null;
        $mnemonic = $signing['type'] === 'mnemonic' ? $signing['mnemonic'] : null;

        if ($upper === 'ETH' || $upper === 'POLYGON' || $upper === 'BSC') {
            return self::buildEvmWallet($walletId, $password, $toAddress, $amount, $fee, $upper, $fromAddress, $privKeyHex);
        }
        if ($upper === 'ETC') {
            return self::buildEvmWallet($walletId, $password, $toAddress, $amount, $fee, 'ETC', $fromAddress, $privKeyHex);
        }
        if ($upper === 'USDT') {
            return self::buildUsdtWallet($walletId, $password, $toAddress, $amount, $fee, $fromAddress, $privKeyHex);
        }
        if ($upper === 'XRP') {
            return self::buildXrpWallet($walletId, $password, $toAddress, $amount, $fee, $fromAddress, $extraId, $privKeyHex);
        }
        if ($upper === 'KASPA') {
            throw new Exception('kaspa-wasm package not found - required for KASPA transactions');
        }

        $addressQuery = ($fromAddress && $addressType)
            ? 'SELECT * FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address = ? LIMIT 1'
            : 'SELECT * FROM wallet_addresses WHERE wallet_id = ? AND coin = ? ORDER BY address_index LIMIT 1';
        $addressParams = ($fromAddress && $addressType)
            ? [$walletId, $coin, $fromAddress]
            : [$walletId, $coin];
        $addresses = Database::query($addressQuery, $addressParams);
        if (count($addresses) === 0) {
            throw new Exception('No sender address found for this coin');
        }

        $selectedAddress = $addresses[0];
        $senderAddress = $fromAddress ? $fromAddress : ($selectedAddress['address'] ?? null);
        $addrType = $addressType ? $addressType : ($selectedAddress['address_type'] ?? 'P2PKH');
        $addrIndex = (int) ($selectedAddress['address_index'] ?? 0);

        $utxos = BlockchainAPI::getUTXOs($senderAddress, $upper);
        if (!$utxos || count($utxos) === 0) {
            throw new Exception('No UTXOs available for this address');
        }

        return self::buildBitcoinFamily($mnemonic, $upper, $toAddress, $amount, $fee, $addrType, $addrIndex, $senderAddress, $utxos, null, $privKeyHex);
    }

    private static function buildEvmWallet($walletId, $password, $toAddress, $amount, $fee, $coin, $fromAddress, $privKeyHex = null) {
        $upper = strtoupper((string) $coin);
        $cfg = self::$evmCoins[$upper] ?? ['coin' => $upper, 'coinPath' => "m/44'/60'/0'/0/", 'chainId' => 1];
        $wallet = Database::query('SELECT * FROM wallets WHERE id = ?', [$walletId]);
        if (count($wallet) === 0) {
            throw new Exception('Wallet not found');
        }
        if ($privKeyHex === null) {
            $signing = self::resolveSigningKey($wallet[0], $password);
            $privKeyHex = $signing['type'] === 'private' ? $signing['privKeyHex'] : null;
            $mnemonic = $signing['type'] === 'mnemonic' ? $signing['mnemonic'] : null;
        } else {
            $mnemonic = null;
        }
        $gasLimit = 21000;
        $gwei = ((float) $fee > 0) ? (float) $fee / $gasLimit * 1e9 : 20.0;
        $chainId = (int) $cfg['chainId'];
        $ethAddress = $privKeyHex !== null
            ? Address::privateKeyToEthereumAddress($privKeyHex)
            : Address::ethereumAddress($mnemonic, 0, $cfg['coinPath']);
        if ($upper === 'ETC') {
            $nonceUrls = ['https://etc.blockscout.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest'];
        } elseif ($chainId === 137) {
            $nonceUrls = [
                'https://polygon.blockscout.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
                'https://api.polygonscan.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
            ];
        } elseif ($chainId === 56) {
            $nonceUrls = [
                'https://bsc.blockscout.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
                'https://api.bscscan.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
            ];
        } else {
            $nonceUrls = [
                'https://eth.blockscout.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
                'https://api.etherscan.io/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
            ];
        }
        $nonce = 0;
        foreach ($nonceUrls as $nonceUrl) {
            try {
                $resp = self::httpGet($nonceUrl);
                if ($resp === null) continue;
                $json = json_decode($resp['body'], true);
                $nonceRes = $json['result'] ?? null;
                if (!is_string($nonceRes) || !preg_match('/^0x[0-9a-fA-F]+$/', $nonceRes)) continue;
                $nonce = (int) hexdec($nonceRes);
                break;
            } catch (Throwable $e) {}
        }

        $result = self::buildEvm([
            'coin' => $cfg['coin'],
            'mnemonic' => $mnemonic,
            'privKeyHex' => $privKeyHex,
            'to' => $toAddress,
            'amount' => $amount,
            'fee' => (string) $gwei,
            'nonce' => $nonce,
            'chainId' => $chainId,
            'gasLimit' => $gasLimit,
            'accountIndex' => 0,
        ], $cfg);

        return [
            'success' => true,
            'rawTxHex' => preg_replace('/^0x/i', '', $result['rawTxHex']),
            'txid' => $result['txid'],
            'fromAddress' => $result['from'],
            'toAddress' => $toAddress,
            'amount' => (string) $amount,
            'fee' => number_format($gasLimit * $gwei * 1e-9, 6, '.', ''),
            'coin' => $cfg['coin'],
        ];
    }

    private static function buildUsdtWallet($walletId, $password, $toAddress, $amount, $fee, $fromAddress, $privKeyHex = null) {
        $wallet = Database::query('SELECT * FROM wallets WHERE id = ?', [$walletId]);
        if (count($wallet) === 0) {
            throw new Exception('Wallet not found');
        }
        if ($privKeyHex === null) {
            $signing = self::resolveSigningKey($wallet[0], $password);
            $privKeyHex = $signing['type'] === 'private' ? $signing['privKeyHex'] : null;
            $mnemonic = $signing['type'] === 'mnemonic' ? $signing['mnemonic'] : null;
        } else {
            $mnemonic = null;
        }
        $gasLimit = 100000;
        $gwei = ((float) $fee > 0) ? (float) $fee / $gasLimit * 1e9 : 20.0;
        $ethAddress = $privKeyHex !== null
            ? Address::privateKeyToEthereumAddress($privKeyHex)
            : Address::ethereumAddress($mnemonic, 0, "m/44'/60'/0'/0/");
        $nonce = 0;
        foreach ([
            'https://eth.blockscout.com/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
            'https://api.etherscan.io/api?module=proxy&action=eth_getTransactionCount&address=' . $ethAddress . '&tag=latest',
        ] as $nonceUrl) {
            try {
                $resp = self::httpGet($nonceUrl);
                if ($resp === null) continue;
                $json = json_decode($resp['body'], true);
                $nonceRes = $json['result'] ?? null;
                if (!is_string($nonceRes) || !preg_match('/^0x[0-9a-fA-F]+$/', $nonceRes)) continue;
                $nonce = (int) hexdec($nonceRes);
                break;
            } catch (Throwable $e) {}
        }

        $usdtContract = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
        $rawAmount = (int) round((float) $amount * 1000000);
        $toPadded = str_pad(preg_replace('/^0x/i', '', $toAddress), 64, '0', STR_PAD_LEFT);
        $amountHex = str_pad(gmp_strval(gmp_init($rawAmount, 10), 16), 64, '0', STR_PAD_LEFT);
        $data = '0xa9059cbb' . $toPadded . $amountHex;

        $cfg = ['coin' => 'USDT', 'coinPath' => "m/44'/60'/0'/0/", 'chainId' => 1];
        $result = self::buildEvm([
            'coin' => 'USDT',
            'mnemonic' => $mnemonic,
            'privKeyHex' => $privKeyHex,
            'to' => $usdtContract,
            'amountWei' => '0',
            'data' => $data,
            'fee' => (string) $gwei,
            'nonce' => $nonce,
            'chainId' => 1,
            'gasLimit' => $gasLimit,
            'accountIndex' => 0,
        ], $cfg);

        return [
            'success' => true,
            'rawTxHex' => preg_replace('/^0x/i', '', $result['rawTxHex']),
            'txid' => $result['txid'],
            'fromAddress' => $result['from'],
            'toAddress' => $toAddress,
            'amount' => (string) $amount,
            'fee' => number_format($gasLimit * $gwei * 1e-9, 6, '.', ''),
            'coin' => 'USDT',
        ];
    }

    private static function buildXrpWallet($walletId, $password, $toAddress, $amount, $fee, $fromAddress, $extraId, $privKeyHex = null) {
        $wallet = Database::query('SELECT * FROM wallets WHERE id = ?', [$walletId]);
        if (count($wallet) === 0) {
            throw new Exception('Wallet not found');
        }
        if ($privKeyHex === null) {
            $signing = self::resolveSigningKey($wallet[0], $password);
            $privKeyHex = $signing['type'] === 'private' ? $signing['privKeyHex'] : null;
            $mnemonic = $signing['type'] === 'mnemonic' ? $signing['mnemonic'] : null;
        } else {
            $mnemonic = null;
        }

        $resp = self::httpPost('https://xrplcluster.com', json_encode([
            'method' => 'account_info',
            'params' => [['account' => $fromAddress, 'ledger_index' => 'current']],
        ]), ['Content-Type: application/json']);
        $json = $resp !== null ? json_decode($resp['body'], true) : null;
        if ($json === null || !isset($json['result']['account_data'])) {
            throw new Exception('Could not fetch account info from XRPL');
        }
        $sequence = (int) $json['result']['account_data']['Sequence'];

        $amountDrops = (string) round((float) $amount * 1000000);
        $feeDrops = (string) round((float) $fee * 1000000);

        $params = [
            'mnemonic' => $mnemonic,
            'privKeyHex' => $privKeyHex,
            'to' => $toAddress,
            'amount' => $amountDrops,
            'fee' => $feeDrops,
            'sequence' => $sequence,
            'lastLedgerSequence' => $sequence + 20,
            'accountIndex' => 0,
        ];
        if ($extraId !== null && $extraId !== '' && is_numeric($extraId) && (int) $extraId > 0) {
            $params['destinationTag'] = (int) $extraId;
        }
        $result = self::buildXrp($params);

        return [
            'success' => true,
            'rawTxHex' => preg_replace('/^0x/i', '', $result['rawTxHex']),
            'txid' => null,
            'fromAddress' => $fromAddress,
            'toAddress' => $toAddress,
            'amount' => $amount,
            'fee' => $fee,
            'coin' => 'XRP',
        ];
    }

    public static function buildBitcoinFamily($mnemonic, $coin, $toAddress, $amount, $fee, $addressType, $addressIndex, $senderAddress, $utxos, $prevTxHexResolver = null, $privKeyHex = null) {
        $upper = strtoupper((string) $coin);
        $config = self::getCoinConfig($upper);
        $coinType = self::$coinTypes[$upper] ?? 0;
        $purpose = self::$purposeMap[$addressType] ?? 44;
        $path = "m/{$purpose}'/{$coinType}'/0'/0/" . (int) $addressIndex;
        if ($privKeyHex !== null) {
            $pubKeyHex = Secp256k1::privateKeyToPublicKey($privKeyHex);
        } else {
            $seed = Bip39::mnemonicToSeed($mnemonic);
            $node = Bip32::derivePath($path, $seed);
            $privKeyHex = $node->getPrivateKey();
            $pubKeyHex = $node->getPublicKey();
        }

        if (!$utxos || count($utxos) === 0) {
            throw new Exception('No UTXOs available for this address');
        }

        $amountSatoshis = (int) round((float) $amount * pow(10, $config['decimals']));
        $feeSatoshis = (int) round((float) $fee * pow(10, $config['decimals']));

        $selectedUTXOs = [];
        $selectedTotal = 0;
        foreach ($utxos as $utxo) {
            $selectedUTXOs[] = $utxo;
            $selectedTotal += (int) ($utxo['value'] ?? $utxo['amount'] ?? 0);
            if ($selectedTotal >= $amountSatoshis + $feeSatoshis) break;
        }
        if ($selectedTotal < $amountSatoshis + $feeSatoshis) {
            throw new Exception('Insufficient balance');
        }
        $changeSatoshis = $selectedTotal - $amountSatoshis - $feeSatoshis;

        $hash160Pub = Address::hash160(hex2bin($pubKeyHex));
        $p2pkhScript = "\x76\xa9\x14" . $hash160Pub . "\x88\xac";
        $redeemScript = "\x00\x14" . $hash160Pub;

        $inputs = [];
        foreach ($selectedUTXOs as $utxo) {
            $txid = (string) ($utxo['txid'] ?? $utxo['tx_hash'] ?? '');
            $vout = (int) ($utxo['vout'] ?? $utxo['tx_output_n'] ?? $utxo['output_index'] ?? 0);
            $value = (int) ($utxo['value'] ?? $utxo['amount'] ?? 0);
            if ($addressType === 'P2WPKH') {
                $inputs[] = ['type' => 'P2WPKH', 'txid' => $txid, 'vout' => $vout, 'value' => $value];
            } elseif ($addressType === 'P2SH') {
                $inputs[] = ['type' => 'P2SH', 'txid' => $txid, 'vout' => $vout, 'value' => $value];
            } else {
                $prevTxHex = $prevTxHexResolver !== null ? call_user_func($prevTxHexResolver, $txid, $upper) : self::getPrevTxHex($txid, $upper);
                if (!$prevTxHex) {
                    throw new Exception("Cannot fetch previous transaction hex for UTXO {$txid}:{$vout}");
                }
                $prevout = self::parsePrevout($prevTxHex, $vout);
                if ($prevout === null) {
                    throw new Exception("Cannot fetch previous transaction hex for UTXO {$txid}:{$vout}");
                }
                $inputs[] = ['type' => 'P2PKH', 'txid' => $txid, 'vout' => $vout, 'value' => $value, 'prevScript' => $prevout['script']];
            }
        }

        $outputs = [];
        $outputs[] = ['value' => $amountSatoshis, 'script' => self::addressToScript($toAddress, $upper)];
        if ($changeSatoshis > 0) {
            $outputs[] = ['value' => $changeSatoshis, 'script' => self::addressToScript($senderAddress, $upper)];
        }

        $sequenceHex = 'feffffff';
        $outputsHex = '';
        foreach ($outputs as $out) {
            $outputsHex .= self::leHex64($out['value']) . self::varIntHex(strlen($out['script'])) . bin2hex($out['script']);
        }
        $outpointHexes = [];
        foreach ($inputs as $in) {
            $outpointHexes[] = self::reverseHex($in['txid']) . self::leHex32($in['vout']);
        }

        $isSegwit = ($addressType === 'P2WPKH' || $addressType === 'P2SH');
        $forkCoin = ($upper === 'BCH' || $upper === 'BSV');
        $scriptSigs = [];
        $witnesses = [];
        $inputHexes = [];

        if ($isSegwit) {
            $hashPrevouts = self::dsha256Hex(implode('', $outpointHexes));
            $hashSequence = self::dsha256Hex(implode('', array_fill(0, count($inputs), $sequenceHex)));
            $hashOutputs = self::dsha256Hex($outputsHex);
            for ($i = 0; $i < count($inputs); $i++) {
                $scriptCode = ($addressType === 'P2SH') ? $redeemScript : $p2pkhScript;
                $preimage = '02000000'
                    . $hashPrevouts
                    . $hashSequence
                    . $outpointHexes[$i]
                    . self::varIntHex(strlen($scriptCode)) . bin2hex($scriptCode)
                    . self::leHex64($inputs[$i]['value'])
                    . $sequenceHex
                    . $hashOutputs
                    . '00000000'
                    . '01000000';
                $hash = self::dsha256Hex($preimage);
                $sig = self::ecdsaSignDer($hash, $privKeyHex) . "\x01";
                $witnesses[] = [$sig, hex2bin($pubKeyHex)];
                if ($addressType === 'P2SH') {
                    $redeemPush = self::pushData($redeemScript);
                    $inputHexes[] = $outpointHexes[$i] . self::varIntHex(strlen($redeemPush)) . bin2hex($redeemPush) . $sequenceHex;
                } else {
                    $inputHexes[] = $outpointHexes[$i] . '00' . $sequenceHex;
                }
            }
        } elseif ($forkCoin) {
            $hashPrevouts = self::dsha256Hex(implode('', $outpointHexes));
            $hashSequence = self::dsha256Hex(implode('', array_fill(0, count($inputs), $sequenceHex)));
            $hashOutputs = self::dsha256Hex($outputsHex);
            for ($i = 0; $i < count($inputs); $i++) {
                if ($upper === 'BCH') {
                    $preimage = '02000000'
                        . $hashPrevouts
                        . $hashSequence
                        . $outpointHexes[$i]
                        . self::varIntHex(strlen($inputs[$i]['prevScript'])) . bin2hex($inputs[$i]['prevScript'])
                        . self::leHex64($inputs[$i]['value'])
                        . $sequenceHex
                        . $hashOutputs
                        . '00000000'
                        . '41000000';
                } else {
                    $allInputsHex = '';
                    for ($j = 0; $j < count($inputs); $j++) {
                        $allInputsHex .= $outpointHexes[$j];
                        if ($j === $i) {
                            $allInputsHex .= self::varIntHex(strlen($inputs[$j]['prevScript'])) . bin2hex($inputs[$j]['prevScript']);
                        } else {
                            $allInputsHex .= '00';
                        }
                        $allInputsHex .= $sequenceHex;
                    }
                    $preimage = '02000000'
                        . self::varIntHex(count($inputs))
                        . $allInputsHex
                        . self::varIntHex(count($outputs))
                        . $outputsHex
                        . '00000000'
                        . '41000000';
                }
                $hash = self::dsha256Hex($preimage);
                $sig = self::ecdsaSignDer($hash, $privKeyHex) . "\x41";
                $scriptSig = self::pushData($sig) . self::pushData(hex2bin($pubKeyHex));
                $scriptSigs[] = $scriptSig;
                $inputHexes[] = $outpointHexes[$i] . self::varIntHex(strlen($scriptSig)) . bin2hex($scriptSig) . $sequenceHex;
            }
        } else {
            for ($i = 0; $i < count($inputs); $i++) {
                $allInputsHex = '';
                for ($j = 0; $j < count($inputs); $j++) {
                    $allInputsHex .= $outpointHexes[$j];
                    if ($j === $i) {
                        $allInputsHex .= self::varIntHex(strlen($inputs[$j]['prevScript'])) . bin2hex($inputs[$j]['prevScript']);
                    } else {
                        $allInputsHex .= '00';
                    }
                    $allInputsHex .= $sequenceHex;
                }
                $preimage = '02000000'
                    . self::varIntHex(count($inputs))
                    . $allInputsHex
                    . self::varIntHex(count($outputs))
                    . $outputsHex
                    . '00000000'
                    . '01000000';
                $hash = self::dsha256Hex($preimage);
                $sig = self::ecdsaSignDer($hash, $privKeyHex) . "\x01";
                $scriptSig = self::pushData($sig) . self::pushData(hex2bin($pubKeyHex));
                $scriptSigs[] = $scriptSig;
                $inputHexes[] = $outpointHexes[$i] . self::varIntHex(strlen($scriptSig)) . bin2hex($scriptSig) . $sequenceHex;
            }
        }

        $body = self::varIntHex(count($inputs)) . implode('', $inputHexes) . self::varIntHex(count($outputs)) . $outputsHex;
        if ($isSegwit) {
            $witnessHex = '';
            foreach ($witnesses as $w) {
                $witnessHex .= self::varIntHex(count($w));
                foreach ($w as $item) {
                    $witnessHex .= self::varIntHex(strlen($item)) . bin2hex($item);
                }
            }
            $rawTxHex = '02000000' . '0001' . $body . $witnessHex . '00000000';
        } else {
            $rawTxHex = '02000000' . $body . '00000000';
        }
        $txid = self::reverseHex(self::dsha256Hex($rawTxHex));

        return [
            'success' => true,
            'rawTxHex' => $rawTxHex,
            'txid' => $txid,
            'fromAddress' => $senderAddress,
            'toAddress' => $toAddress,
            'amount' => $amount,
            'fee' => $feeSatoshis / pow(10, $config['decimals']),
            'coin' => $upper,
        ];
    }

    public static function getPrevTxHex($txid, $coin) {
        $upper = strtoupper((string) $coin);
        $fallbackEndpoints = [
            'BTC' => [
                ['url' => 'https://blockstream.info/api/tx/${txid}/hex', 'format' => 'text'],
                ['url' => 'https://mempool.space/api/tx/${txid}/hex', 'format' => 'text'],
                ['url' => 'https://api.blockcypher.com/v1/btc/main/txs/${txid}?includeHex=true', 'format' => 'json', 'hexField' => 'hex'],
            ],
            'BTCT' => [
                ['url' => 'https://mempool.space/testnet/api/tx/${txid}/hex', 'format' => 'text'],
            ],
            'LTC' => [
                ['url' => 'https://litecoinspace.org/api/tx/${txid}/hex', 'format' => 'text'],
                ['url' => 'https://api.blockcypher.com/v1/ltc/main/txs/${txid}?includeHex=true', 'format' => 'json', 'hexField' => 'hex'],
            ],
            'DOGE' => [
                ['url' => 'https://api.blockcypher.com/v1/doge/main/txs/${txid}?includeHex=true', 'format' => 'json', 'hexField' => 'hex'],
            ],
            'DASH' => [
                ['url' => 'https://api.blockcypher.com/v1/dash/main/txs/${txid}?includeHex=true', 'format' => 'json', 'hexField' => 'hex'],
            ],
        ];

        $endpoints = $fallbackEndpoints[$upper] ?? null;
        if ($endpoints === null) {
            $coinLower = strtolower($upper);
            try {
                $resp = self::httpGet('https://api.blockcypher.com/v1/' . $coinLower . '/main/txs/' . $txid . '?includeHex=true');
                if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                    $data = json_decode($resp['body'], true);
                    if (is_array($data) && isset($data['hex'])) return $data['hex'];
                }
            } catch (Throwable $e) {}
            return null;
        }

        foreach ($endpoints as $ep) {
            try {
                $url = str_replace('${txid}', $txid, $ep['url']);
                $resp = self::httpGet($url);
                if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) continue;
                if ($ep['format'] === 'text') return trim($resp['body']);
                $data = json_decode($resp['body'], true);
                if (!is_array($data)) continue;
                if (isset($ep['hexField'])) {
                    $val = $data;
                    foreach (explode('.', $ep['hexField']) as $k) {
                        $val = is_array($val) ? ($val[$k] ?? null) : null;
                    }
                    if ($val) return $val;
                }
                if (isset($data['hex'])) return $data['hex'];
            } catch (Throwable $e) {}
        }

        $tatumKey = self::envVal('TATUM_API_KEY');
        if ($tatumKey) {
            try {
                $tatumCoin = self::$tatumCoins[$upper] ?? strtolower($upper);
                $resp = self::httpGet('https://api.tatum.io/v3/' . $tatumCoin . '/transaction/' . $txid, ['x-api-key: ' . $tatumKey]);
                if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                    $data = json_decode($resp['body'], true);
                    if (is_array($data) && isset($data['hex'])) return $data['hex'];
                }
            } catch (Throwable $e) {}
        }

        return null;
    }

    public static function broadcast($rawTxHex, $coin) {
        $upper = strtoupper((string) $coin);

        if (in_array($upper, ['BTC', 'BTCT', 'LTC'], true)) {
            try {
                require_once __DIR__ . '/ElectrumClient.php';
                if (ElectrumClient::isAvailable()) {
                    $electrum = new ElectrumClient($upper);
                    $electrum->discoverServers();
                    return $electrum->broadcast($rawTxHex);
                }
            } catch (Throwable $e) {
                error_log("Electrum broadcast error for {$upper}: " . $e->getMessage());
            }
        }

        $tatumKey = self::envVal('TATUM_API_KEY');
        if ($tatumKey && $upper !== 'BTCT') {
            try {
                $tatumCoin = self::$tatumCoins[$upper] ?? 'bitcoin';
                $resp = self::httpPost('https://api.tatum.io/v3/' . $tatumCoin . '/broadcast', json_encode(['txData' => $rawTxHex]), ['x-api-key: ' . $tatumKey, 'Content-Type: application/json']);
                if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                    return ['tx' => ['hash' => trim($resp['body'])], 'confirmed' => false];
                }
            } catch (Throwable $e) {
                error_log('Tatum broadcast fallback: ' . $e->getMessage());
            }
        }

        $cryptoapisKey = self::envVal('CRYPTOAPIS_API_KEY');
        if ($cryptoapisKey && $upper === 'BTC') {
            try {
                $blockchain = self::$cryptoapisCoins[$upper] ?? 'bitcoin';
                $hex = preg_match('/^0x/i', $rawTxHex) ? $rawTxHex : '0x' . $rawTxHex;
                $resp = self::httpPost('https://rest.cryptoapis.io/broadcast-transactions/' . $blockchain . '/mainnet', json_encode(['data' => ['item' => ['signedTransactionHex' => $hex]]]), ['X-API-Key: ' . $cryptoapisKey, 'Content-Type: application/json']);
                if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                    $data = json_decode($resp['body'], true);
                    $txid = $data['data']['item']['transactionId'] ?? null;
                    if ($txid) return ['tx' => ['hash' => $txid], 'confirmed' => false];
                }
            } catch (Throwable $e) {
                error_log('CryptoAPIs broadcast failed, using free APIs');
            }
        }

        if ($upper === 'ETH' || $upper === 'USDT' || $upper === 'POLYGON' || $upper === 'BSC') {
            $scanHosts = $upper === 'POLYGON'
                ? ['https://polygon.blockscout.com', 'https://api.polygonscan.com']
                : ($upper === 'BSC' ? ['https://bsc.blockscout.com', 'https://api.bscscan.com'] : ['https://eth.blockscout.com', 'https://api.etherscan.io']);
            foreach ($scanHosts as $scanHost) {
                $resp = self::httpGet($scanHost . '/api?module=proxy&action=eth_sendRawTransaction&hex=0x' . $rawTxHex);
                if ($resp === null) continue;
                $data = json_decode($resp['body'], true);
                if (is_array($data) && is_string($data['result'] ?? null) && preg_match('/^0x[0-9a-fA-F]{64}$/', $data['result'])) {
                    return ['tx' => ['hash' => $data['result']], 'confirmed' => false];
                }
            }
            throw new Exception($upper . ' broadcast failed: all endpoints rejected the transaction');
        }

        if ($upper === 'ETC') {
            $resp = self::httpGet('https://etc.blockscout.com/api?module=proxy&action=eth_sendRawTransaction&hex=0x' . $rawTxHex);
            if ($resp === null) throw new Exception('ETC broadcast failed: network error');
            $data = json_decode($resp['body'], true);
            if (is_array($data) && !empty($data['result'])) {
                return ['tx' => ['hash' => $data['result']], 'confirmed' => false];
            }
            throw new Exception((is_array($data) && isset($data['error']['message'])) ? $data['error']['message'] : json_encode($data));
        }

        if ($upper === 'XRP') {
            $resp = self::httpPost('https://xrplcluster.com', json_encode([
                'method' => 'submit',
                'params' => [['tx_blob' => $rawTxHex]],
            ]), ['Content-Type: application/json']);
            if ($resp === null) throw new Exception('XRP broadcast failed: network error');
            $data = json_decode($resp['body'], true);
            if (is_array($data) && isset($data['result']['tx_json']['hash'])) {
                return ['tx' => ['hash' => $data['result']['tx_json']['hash']], 'confirmed' => false];
            }
            throw new Exception((is_array($data) && isset($data['result']['error_message'])) ? $data['result']['error_message'] : json_encode($data));
        }

        if ($upper === 'KASPA') {
            $resp = self::httpPost('https://api.kaspa.org/transactions', $rawTxHex, ['Content-Type: text/plain']);
            if ($resp === null || $resp['status'] < 200 || $resp['status'] >= 300) {
                throw new Exception('KASPA broadcast error: ' . ($resp === null ? 'network error' : $resp['status']));
            }
            $txid = trim($resp['body']);
            if ($txid) return ['tx' => ['hash' => $txid], 'confirmed' => false];
            throw new Exception('KASPA broadcast error: empty response');
        }

        $broadcastEndpoints = [
            'BTC' => [
                ['https://blockstream.info/api/tx', 'text/plain', null],
                ['https://mempool.space/api/tx', 'text/plain', null],
                ['https://api.blockcypher.com/v1/btc/main/txs/push', 'json', ['tx' => $rawTxHex]],
            ],
            'BTCT' => [
                ['https://mempool.space/testnet/api/tx', 'text/plain', null],
            ],
            'LTC' => [
                ['https://litecoinspace.org/api/tx', 'text/plain', null],
                ['https://api.blockcypher.com/v1/ltc/main/txs/push', 'json', ['tx' => $rawTxHex]],
            ],
            'DOGE' => [
                ['https://api.blockcypher.com/v1/doge/main/txs/push', 'json', ['tx' => $rawTxHex]],
            ],
            'BCH' => [['https://api.blockcypher.com/v1/bch/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'DASH' => [
                ['https://api.blockcypher.com/v1/dash/main/txs/push', 'json', ['tx' => $rawTxHex]],
            ],
            'DGB' => [['https://api.blockcypher.com/v1/dgb/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'BTG' => [['https://api.blockcypher.com/v1/btg/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'RVN' => [['https://api.blockcypher.com/v1/rvn/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'ZEC' => [['https://api.blockcypher.com/v1/zec/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'BSV' => [['https://api.blockcypher.com/v1/bsv/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'XVG' => [['https://api.blockcypher.com/v1/xvg/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'QTUM' => [['https://api.blockcypher.com/v1/qtum/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'VTC' => [['https://api.blockcypher.com/v1/vtc/main/txs/push', 'json', ['tx' => $rawTxHex]]],
            'KMD' => [['https://api.blockcypher.com/v1/kmd/main/txs/push', 'json', ['tx' => $rawTxHex]]],
        ];

        $endpoints = $broadcastEndpoints[$upper] ?? null;
        if (!$endpoints) {
            throw new Exception('No broadcast endpoint for ' . $coin);
        }
        $lastError = null;
        foreach ($endpoints as $ep) {
            try {
                if ($ep[1] === 'json') {
                    $resp = self::httpPost($ep[0], json_encode($ep[2]), ['Content-Type: application/json']);
                } else {
                    $resp = self::httpPost($ep[0], $rawTxHex, ['Content-Type: text/plain']);
                }
                if ($resp !== null && $resp['status'] >= 200 && $resp['status'] < 300) {
                    $respText = trim($resp['body']);
                    $txid = null;
                    $j = json_decode($respText, true);
                    if (is_array($j)) {
                        $txid = $j['data']['txid'] ?? $j['txid'] ?? $j['tx']['hash'] ?? $j['hash'] ?? $j['result'] ?? null;
                        if (is_array($txid)) $txid = null;
                    } else {
                        $txid = $respText;
                    }
                    if (is_string($txid) && preg_match('/^[0-9a-fA-F]{64}$/', $txid)) {
                        return ['tx' => ['hash' => $txid], 'confirmed' => false];
                    }
                }
                $lastError = 'Status ' . ($resp === null ? 'network error' : $resp['status']);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }
        throw new Exception('All broadcast endpoints failed for ' . $coin . ': ' . $lastError);
    }

    private static function ecdsaSignDer($msgHashHex, $privKeyHex) {
        $sig = Secp256k1::ecdsaSign(hex2bin($msgHashHex), hex2bin($privKeyHex));
        return self::derEncode($sig['r'], $sig['s']);
    }

    private static function dsha256Hex($hex) {
        return bin2hex(hash('sha256', hash('sha256', hex2bin($hex), true), true));
    }

    private static function reverseHex($hex) {
        return implode('', array_reverse(str_split($hex, 2)));
    }

    private static function varIntHex($n) {
        $n = (int) $n;
        if ($n < 0xfd) return sprintf('%02x', $n);
        if ($n <= 0xffff) return 'fd' . bin2hex(pack('v', $n));
        if ($n <= 0xffffffff) return 'fe' . bin2hex(pack('V', $n));
        return 'ff' . self::leHex64($n);
    }

    private static function leHex32($n) {
        return bin2hex(pack('V', (int) $n & 0xffffffff));
    }

    private static function leHex64($n) {
        $hex = gmp_strval(gmp_init((string) $n, 10), 16);
        $hex = str_pad($hex, 16, '0', STR_PAD_LEFT);
        return self::reverseHex($hex);
    }

    private static function pushData($bytes) {
        $len = strlen($bytes);
        if ($len < 0x4c) return chr($len) . $bytes;
        if ($len <= 0xff) return "\x4c" . chr($len) . $bytes;
        if ($len <= 0xffff) return "\x4d" . pack('v', $len) . $bytes;
        return "\x4e" . pack('V', $len) . $bytes;
    }

    private static function addressToScript($address, $coin) {
        $upper = strtoupper((string) $coin);
        $net = self::$customNetworks[$upper] ?? self::$customNetworks['BTC'];
        $hrp = $net['bech32'];
        if ($upper === 'BCH' || $upper === 'BSV') {
            $cashScript = self::decodeCashAddrScript($address);
            if ($cashScript !== null) return $cashScript;
        }
        if (preg_match('/^(' . preg_quote($hrp, '/') . ')1[02-9ac-hj-np-z]+$/i', $address)) {
            $decoded = Bech32::decode($address);
            if ($decoded !== null && isset($decoded['data']) && count($decoded['data']) >= 2) {
                $program = Bech32::fromWords(array_slice($decoded['data'], 1));
                if ($program !== null && count($program) === 20) return "\x00\x14" . implode('', array_map('chr', $program));
                if ($program !== null && count($program) === 32) return "\x00\x20" . implode('', array_map('chr', $program));
            }
            throw new Exception('Unsupported address format: ' . $address);
        }

        $decoded = Base58::base58CheckDecode($address);
        if ($upper === 'ZEC' && strlen($decoded) === 22 && ord($decoded[0]) === 0x1c && ord($decoded[1]) === 0xb8) {
            return "\x76\xa9\x14" . substr($decoded, 2) . "\x88\xac";
        }
        $versionByte = ord($decoded[0]);
        $hash = substr($decoded, 1);
        if ($versionByte === $net['pubKeyHash'] && strlen($hash) === 20) {
            return "\x76\xa9\x14" . $hash . "\x88\xac";
        }
        if ($versionByte === $net['scriptHash'] && strlen($hash) === 20) {
            return "\xa9\x14" . $hash . "\x87";
        }
        throw new Exception('Unsupported address format: ' . $address);
    }

    private static function parsePrevout($txHex, $vout) {
        $txHex = preg_replace('/^0x/i', '', trim($txHex));
        if (!ctype_xdigit($txHex) || strlen($txHex) % 2 !== 0) return null;
        $data = $txHex;
        $pos = 0;
        $len = strlen($data);
        $readU8 = function () use (&$data, &$pos, $len) {
            if ($pos + 2 > $len) return null;
            $v = hexdec(substr($data, $pos, 2));
            $pos += 2;
            return $v;
        };
        $readVarInt = function () use (&$readU8, &$data, &$pos, $len) {
            $first = $readU8();
            if ($first === null) return null;
            if ($first < 0xfd) return $first;
            if ($first === 0xfd) {
                if ($pos + 4 > $len) return null;
                $s = substr($data, $pos, 4);
                $pos += 4;
                return hexdec(self::reverseHex($s));
            }
            if ($first === 0xfe) {
                if ($pos + 8 > $len) return null;
                $s = substr($data, $pos, 8);
                $pos += 8;
                return hexdec(self::reverseHex($s));
            }
            if ($pos + 16 > $len) return null;
            $s = substr($data, $pos, 16);
            $pos += 16;
            return gmp_intval(gmp_init(self::reverseHex($s), 16));
        };
        $pos += 8;
        if (substr($data, $pos, 2) === '00' && substr($data, $pos + 2, 2) !== '00') {
            $pos += 4;
        }
        $inCount = $readVarInt();
        if ($inCount === null) return null;
        for ($i = 0; $i < $inCount; $i++) {
            if ($pos + 72 > $len) return null;
            $pos += 64;
            $pos += 8;
            $sl = $readVarInt();
            if ($sl === null || $pos + $sl * 2 > $len) return null;
            $pos += $sl * 2;
            if ($pos + 8 > $len) return null;
            $pos += 8;
        }
        $outCount = $readVarInt();
        if ($outCount === null) return null;
        $target = null;
        for ($i = 0; $i < $outCount; $i++) {
            if ($pos + 16 > $len) return null;
            $valueHex = substr($data, $pos, 16);
            $pos += 16;
            $value = gmp_intval(gmp_init(self::reverseHex($valueHex), 16));
            $sl = $readVarInt();
            if ($sl === null || $pos + $sl * 2 > $len) return null;
            $script = hex2bin(substr($data, $pos, $sl * 2));
            $pos += $sl * 2;
            if ($i === $vout) {
                $target = ['value' => $value, 'script' => $script];
            }
        }
        return $target;
    }

    private static function decodeCashAddrScript($address) {
        $addr = trim($address);
        $lower = strtolower($addr);
        if ($lower !== $addr && strtoupper($addr) !== $addr) return null;
        $prefix = null;
        $payload = null;
        foreach (['bitcoincash', 'bchtest', 'bchreg'] as $p) {
            if (strpos($lower, $p . ':') === 0) {
                $prefix = $p;
                $payload = substr($addr, strlen($p) + 1);
                break;
            }
        }
        if ($payload === null) {
            foreach (['bitcoincash', 'bchtest', 'bchreg'] as $p) {
                if (preg_match('/^' . $p . '[qpzry9x8gf2tvdw0s3jn54khce6mua7l]+$/i', $addr)) {
                    $prefix = $p;
                    $payload = substr($addr, strlen($p));
                    break;
                }
            }
        }
        if ($prefix === null || $payload === '') return null;
        $payload = strtolower($payload);

        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $map = [];
        for ($i = 0; $i < 32; $i++) $map[$charset[$i]] = $i;

        $prefixData = [];
        for ($i = 0; $i < strlen($prefix); $i++) {
            $prefixData[] = ord($prefix[$i]) & 0x1f;
        }

        $payloadValues = [];
        $n = strlen($payload);
        for ($i = 0; $i < $n; $i++) {
            if (!isset($map[$payload[$i]])) return null;
            $payloadValues[] = $map[$payload[$i]];
        }

        $chk = 1;
        foreach ($prefixData as $v) $chk = self::cashPolyMod($chk, $v);
        $chk = self::cashPolyMod($chk, 0);
        foreach ($payloadValues as $v) $chk = self::cashPolyMod($chk, $v);
        if ($chk !== 1) return null;

        $dataWords = array_slice($payloadValues, 0, count($payloadValues) - 8);
        $bits = '';
        foreach ($dataWords as $w) $bits .= str_pad(decbin($w), 5, '0', STR_PAD_LEFT);
        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }
        if (strlen($bytes) < 2) return null;
        $ver = ord($bytes[0]);
        $type = ($ver >> 3) & 0x0f;
        $size = $ver & 0x07;
        $hash = substr($bytes, 1);
        if ($type === 0 && $size === 0 && strlen($hash) === 20) {
            return "\x76\xa9\x14" . $hash . "\x88\xac";
        }
        if ($type === 1 && $size === 0 && strlen($hash) === 20) {
            return "\xa9\x14" . $hash . "\x87";
        }
        return null;
    }

    private static function cashPolyMod($chk, $value) {
        $generator = [0x98f2bc8e61, 0x79b76d99e2, 0xf33e5fb3c4, 0xae2eabe2a8, 0x1e4f43e470];
        $top = $chk >> 35;
        $chk = (($chk & 0x07ffffffff) << 5) ^ $value;
        for ($i = 0; $i < 5; $i++) {
            if (($top >> $i) & 1) $chk ^= $generator[$i];
        }
        return $chk;
    }

    private static function httpGet($url, $headers = []) {
        return self::httpRequestTb($url, 'GET', null, $headers);
    }

    private static function httpPost($url, $body, $headers = []) {
        return self::httpRequestTb($url, 'POST', $body, $headers);
    }

    private static function httpRequestTb($url, $method = 'GET', $body = null, $headers = [], $timeout = 15) {
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (PruneBit/1.0)',
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
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false) return null;
        return ['status' => $httpCode, 'body' => $response];
    }

    private static function envVal($key) {
        $v = getenv($key);
        if ($v !== false && $v !== '') return $v;
        if (function_exists('env')) return env($key);
        return null;
    }
}
