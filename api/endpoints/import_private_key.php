<?php

if (!function_exists('EndpointNormalizeHexPrivateKey')) {
    function EndpointNormalizeHexPrivateKey($privateKey) {
        return strpos($privateKey, '0x') === 0 ? substr($privateKey, 2) : $privateKey;
    }
}

if (!function_exists('EndpointIsValidScalar')) {
    function EndpointIsValidScalar($privHex) {
        if (!preg_match('/^[0-9a-fA-F]{64}$/', $privHex)) {
            return false;
        }
        $k = gmp_init($privHex, 16);
        $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        return gmp_cmp($k, 0) > 0 && gmp_cmp($k, $n) < 0;
    }
}

if (!function_exists('EndpointIsValidHexPrivateKey')) {
    function EndpointIsValidHexPrivateKey($privateKey) {
        try {
            $normalized = EndpointNormalizeHexPrivateKey($privateKey);
            return preg_match('/^[0-9a-fA-F]{64}$/', $normalized) === 1 && EndpointIsValidScalar($normalized);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('EndpointIsValidWif')) {
    function EndpointIsValidWif($wif) {
        try {
            $decoded = Base58::base58CheckDecode($wif);
            $version = ord($decoded[0]);
            return in_array($version, [0x80, 0xef, 0x30, 0x9e, 0x9f, 0x4c, 0x1e, 0x3c, 0x26, 0xb0, 0xcc, 0xbc], true);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('EndpointP2shAddress')) {
    function EndpointP2shAddress($pubkeyHex, $coin) {
        $versions = ['BTC' => 0x05, 'BTCT' => 0xc4, 'LTC' => 0x32, 'DGB' => 0x05, 'RVN' => 0x3C, 'BTG' => 0x26, 'ZEC' => 0x1D];
        $coinUpper = strtoupper($coin);
        $hash = Address::hash160(hex2bin($pubkeyHex));
        $redeemScript = "\x00\x14" . $hash;
        $scriptHash = Address::hash160($redeemScript);
        $versionByte = chr(isset($versions[$coinUpper]) ? $versions[$coinUpper] : 0x05);
        return Base58::base58CheckEncode($versionByte . $scriptHash);
    }
}

if (!function_exists('EndpointP2wpkhAddress')) {
    function EndpointP2wpkhAddress($pubkeyHex, $coin) {
        $prefixes = ['BTC' => 'bc', 'BTCT' => 'tb', 'LTC' => 'ltc', 'DOGE' => 'doge', 'BCH' => 'bitcoincash', 'DASH' => 'dash', 'DGB' => 'dgb', 'BTG' => 'btg', 'RVN' => 'rvn', 'ETH' => 'eth'];
        $coinUpper = strtoupper($coin);
        $hrp = isset($prefixes[$coinUpper]) ? $prefixes[$coinUpper] : 'bc';
        $hash = Address::hash160(hex2bin($pubkeyHex));
        $words = array_merge([0], Bech32::toWords($hash));
        return Bech32::encode($hrp, $words);
    }
}

if (!function_exists('EndpointUpsertAddress')) {
    function EndpointUpsertAddress($walletId, $coin, $addressType, $addressIndex, $address) {
        $existing = Database::query(
            'SELECT id FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_type = ? AND address_index = ?',
            [$walletId, $coin, $addressType, $addressIndex]
        );
        if (count($existing) > 0) {
            Database::execute('UPDATE wallet_addresses SET address = ? WHERE id = ?', [$address, $existing[0]['id']]);
        } else {
            Database::execute(
                'INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)',
                [$walletId, $coin, $addressType, $addressIndex, $address]
            );
        }
    }
}

Api::requirePost();

try {
    $captchaToken = Api::body('captcha_token');
    $captchaCode = Api::body('captcha_code');
    $captchaResult = Captcha::verify($captchaToken, $captchaCode);
    if (!$captchaResult['valid']) {
        jsonResponse(['error' => $captchaResult['error']], 400);
    }

    $privateKey = Api::body('privateKey');
    $coin = Api::body('coin');
    $password = Api::body('password');

    if (!$privateKey || !$coin || !$password) {
        jsonResponse(['error' => 'Please enter the private key, select a coin, and create a wallet PIN.'], 400);
    }

    $coinUpper = strtoupper($coin);
    $privateKeyForId = EndpointIsValidHexPrivateKey($privateKey) ? EndpointNormalizeHexPrivateKey($privateKey) : $privateKey;
    $walletId = 'wallet_pk_' . substr(hash('sha256', $privateKeyForId . ':' . $coinUpper), 0, 32);
    $existingWallets = Database::query('SELECT id, password_hash FROM wallets WHERE id = ?', [$walletId]);

    if (strpos($privateKey, 'xprv') === 0 || strpos($privateKey, 'tprv') === 0) {
        try {
            $node = Bip32::fromBase58($privateKey);

            $allAddresses = [];

            if (in_array($coinUpper, ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'])) {
                if ($node->getPrivateKey() === null) {
                    jsonResponse(['error' => 'xprv private key required for ETH address'], 400);
                }
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::privateKeyToEthereumAddress($node->getPrivateKey())];
            } elseif ($coinUpper === 'BCH') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::publicKeyToBitcoinCashAddress($node->getPublicKey())];
            } elseif ($coinUpper === 'KASPA') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::kaspaAddressFromPublicKey($node->getPublicKey())];
            } elseif ($coinUpper === 'XRP') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::xrpAddress($node->getPublicKey())];
            } else {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::pubkeyToP2PKH($node->getPublicKey(), $coinUpper)];
            }

            if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'BTG', 'RVN', 'DGB'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2SH', 0, EndpointP2shAddress($node->getPublicKey(), $coinUpper)];
            }

            if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'DGB', 'BTG', 'RVN'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2WPKH', 0, EndpointP2wpkhAddress($node->getPublicKey(), $coinUpper)];
            }

            $profile = ['default_coin' => $coinUpper, 'key_type' => 'HD'];
            $passwordHash = Wallet::hashPassword($password);

            if (count($existingWallets) > 0) {
                $wallet = $existingWallets[0];
                if (!Wallet::verifyPassword($password, $wallet['password_hash'])) {
                    jsonResponse(['error' => 'Invalid PIN for existing wallet'], 401);
                }
                $token = Session::create($walletId, 24, Session::metaFromRequest());
                foreach ($allAddresses as $row) {
                    EndpointUpsertAddress($row[0], $row[1], $row[2], $row[3], $row[4]);
                }
                $responseAddresses = [];
                foreach ($allAddresses as $a) {
                    $responseAddresses[] = ['coin' => $a[1], 'type' => $a[2], 'address' => $a[4], 'index' => 0];
                }
                jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => true, 'addresses' => $responseAddresses, 'keyType' => 'HD']);
            }

            Database::execute(
                'INSERT INTO wallets (id, password_hash, profile, id_coin) VALUES (?, ?, ?, ?)',
                [$walletId, $passwordHash, json_encode($profile), $coinUpper]
            );

            foreach ($allAddresses as $row) {
                Database::execute(
                    'INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)',
                    $row
                );
            }

            $token = Session::create($walletId, 24, Session::metaFromRequest());
            $responseAddresses = [];
            foreach ($allAddresses as $a) {
                $responseAddresses[] = ['coin' => $a[1], 'type' => $a[2], 'address' => $a[4], 'index' => 0];
            }
            jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => false, 'addresses' => $responseAddresses, 'keyType' => 'HD']);
        } catch (Throwable $bip32Err) {
            jsonResponse(['error' => 'Invalid xprv format'], 400);
        }
    }

    if (EndpointIsValidHexPrivateKey($privateKey)) {
        try {
            $privateKeyBytes = EndpointNormalizeHexPrivateKey($privateKey);
            $pubkey = Secp256k1::privateKeyToPublicKey($privateKeyBytes);
            $fullPubkey = $pubkey;
            $allAddresses = [];

            if (in_array($coinUpper, ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::privateKeyToEthereumAddress(EndpointNormalizeHexPrivateKey($privateKey))];
            } elseif ($coinUpper === 'BCH') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::publicKeyToBitcoinCashAddress($fullPubkey)];
            } elseif ($coinUpper === 'KASPA') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::kaspaAddressFromPublicKey($fullPubkey)];
            } elseif ($coinUpper === 'XRP') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::xrpAddress($fullPubkey)];
            } else {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::pubkeyToP2PKH($fullPubkey, $coinUpper)];
            }

            if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'BTG', 'RVN', 'DGB'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2SH', 0, EndpointP2shAddress($fullPubkey, $coinUpper)];
            }

            if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'DGB', 'BTG', 'RVN'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2WPKH', 0, EndpointP2wpkhAddress($fullPubkey, $coinUpper)];
            }

            if (count($existingWallets) > 0) {
                $wallet = $existingWallets[0];
                if (!Wallet::verifyPassword($password, $wallet['password_hash'])) {
                    jsonResponse(['error' => 'Invalid PIN for existing wallet'], 401);
                }
                $token = Session::create($walletId, 24, Session::metaFromRequest());
                foreach ($allAddresses as $row) {
                    EndpointUpsertAddress($row[0], $row[1], $row[2], $row[3], $row[4]);
                }
                $responseAddresses = [];
                foreach ($allAddresses as $a) {
                    $responseAddresses[] = ['coin' => $a[1], 'type' => $a[2], 'address' => $a[4], 'index' => 0];
                }
                jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => true, 'addresses' => $responseAddresses, 'keyType' => 'HEX']);
            }
            $passwordHash = Wallet::hashPassword($password);
            $profile = json_encode(['default_coin' => $coinUpper, 'key_type' => 'HEX']);

            Database::execute(
                'INSERT INTO wallets (id, password_hash, profile, id_coin) VALUES (?, ?, ?, ?)',
                [$walletId, $passwordHash, $profile, $coinUpper]
            );

            foreach ($allAddresses as $row) {
                Database::execute(
                    'INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)',
                    $row
                );
            }

            $token = Session::create($walletId, 24, Session::metaFromRequest());
            $responseAddresses = [];
            foreach ($allAddresses as $a) {
                $responseAddresses[] = ['coin' => $a[1], 'type' => $a[2], 'address' => $a[4], 'index' => 0];
            }
            jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => false, 'addresses' => $responseAddresses, 'keyType' => 'HEX']);
        } catch (Throwable $hexErr) {
            error_log('Hex private key processing error: ' . ($hexErr->getCode() ? $hexErr->getCode() : 'hex_import_failed'));
            jsonResponse(['error' => 'Invalid hex private key format'], 400);
        }
    }

    if (EndpointIsValidWif($privateKey)) {
        try {
            $decoded = Base58::base58CheckDecode($privateKey);
            $privateKeyBytes = substr($decoded, 1, 32);

            if (strlen($privateKeyBytes) !== 32 || !EndpointIsValidScalar(bin2hex($privateKeyBytes))) {
                jsonResponse(['error' => 'Invalid WIF private key format'], 400);
            }

            $pubkey = Secp256k1::privateKeyToPublicKey(bin2hex($privateKeyBytes));
            $fullPubkey = $pubkey;
            $allAddresses = [];

            if (in_array($coinUpper, ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::privateKeyToEthereumAddress(bin2hex($privateKeyBytes))];
            } elseif ($coinUpper === 'BCH') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::publicKeyToBitcoinCashAddress($fullPubkey)];
            } elseif ($coinUpper === 'KASPA') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::kaspaAddressFromPublicKey($fullPubkey)];
            } elseif ($coinUpper === 'XRP') {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::xrpAddress($fullPubkey)];
            } else {
                $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::pubkeyToP2PKH($fullPubkey, $coinUpper)];
            }

            if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'BTG', 'RVN', 'DGB'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2SH', 0, EndpointP2shAddress($fullPubkey, $coinUpper)];
            }

            if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'DGB', 'BTG', 'RVN'])) {
                $allAddresses[] = [$walletId, $coinUpper, 'P2WPKH', 0, EndpointP2wpkhAddress($fullPubkey, $coinUpper)];
            }

            if (count($existingWallets) > 0) {
                $wallet = $existingWallets[0];
                if (!Wallet::verifyPassword($password, $wallet['password_hash'])) {
                    jsonResponse(['error' => 'Invalid PIN for existing wallet'], 401);
                }
                $token = Session::create($walletId, 24, Session::metaFromRequest());
                foreach ($allAddresses as $row) {
                    EndpointUpsertAddress($row[0], $row[1], $row[2], $row[3], $row[4]);
                }
                $responseAddresses = [];
                foreach ($allAddresses as $a) {
                    $responseAddresses[] = ['coin' => $a[1], 'type' => $a[2], 'address' => $a[4], 'index' => 0];
                }
                jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => true, 'addresses' => $responseAddresses, 'keyType' => 'WIF']);
            }
            $passwordHash = Wallet::hashPassword($password);
            $profile = json_encode(['default_coin' => $coinUpper, 'key_type' => 'WIF']);

            Database::execute(
                'INSERT INTO wallets (id, password_hash, profile, id_coin) VALUES (?, ?, ?, ?)',
                [$walletId, $passwordHash, $profile, $coinUpper]
            );

            foreach ($allAddresses as $row) {
                Database::execute(
                    'INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)',
                    $row
                );
            }

            $token = Session::create($walletId, 24, Session::metaFromRequest());
            $responseAddresses = [];
            foreach ($allAddresses as $a) {
                $responseAddresses[] = ['coin' => $a[1], 'type' => $a[2], 'address' => $a[4], 'index' => 0];
            }
            jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => false, 'addresses' => $responseAddresses, 'keyType' => 'WIF']);
        } catch (Throwable $ecErr) {
            error_log('WIF processing error: ' . ($ecErr->getCode() ? $ecErr->getCode() : 'wif_import_failed'));
            jsonResponse(['error' => 'Invalid WIF format'], 400);
        }
    }

    jsonResponse(['error' => 'Invalid private key format. Must be WIF, hex private key, or xprv.'], 400);
} catch (Throwable $e) {
    error_log('Import private key error: ' . ($e->getCode() ? $e->getCode() : 'import_pk_failed'));
    jsonResponse(['error' => 'Authentication failed'], 500);
}
