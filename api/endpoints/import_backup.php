<?php

$importBackupIsTruthy = function ($v) {
    if ($v === null || $v === false || $v === '' || $v === '0' || $v === 0 || $v === 0.0) {
        return false;
    }
    return true;
};

$importBackupP2PKH = function ($pubkeyHex, $coin) {
    $versions = ['BTC' => 0x00, 'BTCT' => 0x6f, 'LTC' => 0x30, 'DOGE' => 0x1E, 'DASH' => 0x4C, 'DGB' => 0x1E, 'RVN' => 0x3C, 'BTG' => 0x26, 'BCH' => 0x00, 'ETH' => 0x00, 'ZEC' => 0x1C, 'BSV' => 0x00, 'XVG' => 0x1E, 'QTUM' => 0x3A, 'VTC' => 0x47, 'KMD' => 0x3C];
    $coinUpper = strtoupper($coin);
    $prefixBytes = $coinUpper === 'ZEC' ? "\x1c\xb8" : chr(isset($versions[$coinUpper]) ? $versions[$coinUpper] : 0x00);
    return Base58::base58CheckEncode($prefixBytes . Address::hash160(hex2bin($pubkeyHex)));
};

$importBackupP2SH = function ($pubkeyHex, $coin) {
    $versions = ['BTC' => 0x05, 'BTCT' => 0xc4, 'LTC' => 0x32, 'DGB' => 0x05, 'RVN' => 0x3C, 'BTG' => 0x26, 'ZEC' => 0x1D];
    $coinUpper = strtoupper($coin);
    $hash = Address::hash160(hex2bin($pubkeyHex));
    $redeemScript = "\x00\x14" . $hash;
    $scriptHash160 = Address::hash160($redeemScript);
    $versionByte = chr(isset($versions[$coinUpper]) ? $versions[$coinUpper] : 0x05);
    return Base58::base58CheckEncode($versionByte . $scriptHash160);
};

$importBackupP2WPKH = function ($pubkeyHex, $coin) {
    $prefixes = ['BTC' => 'bc', 'BTCT' => 'tb', 'LTC' => 'ltc', 'DOGE' => 'doge', 'BCH' => 'bitcoincash', 'DASH' => 'dash', 'DGB' => 'dgb', 'BTG' => 'btg', 'RVN' => 'rvn', 'ETH' => 'eth'];
    $coinUpper = strtoupper($coin);
    $hrp = isset($prefixes[$coinUpper]) ? $prefixes[$coinUpper] : 'bc';
    $hash = Address::hash160(hex2bin($pubkeyHex));
    $words = array_merge([0], Bech32::toWords($hash));
    return Bech32::encode($hrp, $words);
};

$importBackupIsValidHexPrivateKey = function ($privateKey) {
    try {
        $normalized = strpos($privateKey, '0x') === 0 ? substr($privateKey, 2) : $privateKey;
        if (preg_match('/^[0-9a-fA-F]{64}$/', $normalized) !== 1) {
            return false;
        }
        $k = gmp_init($normalized, 16);
        return gmp_cmp($k, 1) >= 0 && gmp_cmp($k, gmp_init(Secp256k1::$N, 16)) < 0;
    } catch (Throwable $e) {
        return false;
    }
};

$importBackupIsValidWif = function ($wif) {
    try {
        $decoded = Base58::base58CheckDecode($wif);
        return in_array(ord($decoded[0]), [0x80, 0xef, 0x30, 0x9e, 0x9f, 0x4c, 0x1e, 0x3c, 0x26, 0xb0, 0xcc], true);
    } catch (Throwable $e) {
        return false;
    }
};

$importBackupImportPrivateKeyWallet = function ($privateKey, $coin, $password) use ($importBackupIsValidHexPrivateKey, $importBackupIsValidWif, $importBackupP2PKH, $importBackupP2SH, $importBackupP2WPKH) {
    $coinUpper = strtoupper($coin);
    $privateKeyForId = $importBackupIsValidHexPrivateKey($privateKey) ? (strpos($privateKey, '0x') === 0 ? substr($privateKey, 2) : $privateKey) : $privateKey;
    $walletId = 'wallet_pk_' . substr(hash('sha256', $privateKeyForId . ':' . $coinUpper), 0, 32);

    $existing = Database::query('SELECT id FROM wallets WHERE id = ?', [$walletId]);
    if (count($existing) > 0) {
        return ['walletId' => $walletId, 'alreadyExists' => true];
    }

    $pubkeyHex = null;
    if ($importBackupIsValidHexPrivateKey($privateKey)) {
        $normalized = strpos($privateKey, '0x') === 0 ? substr($privateKey, 2) : $privateKey;
        $pubkeyHex = Secp256k1::privateKeyToPublicKey($normalized);
    } elseif ($importBackupIsValidWif($privateKey)) {
        $decoded = Base58::base58CheckDecode($privateKey);
        $privateKeyBytes = substr($decoded, 1, 32);
        $pubkeyHex = Secp256k1::privateKeyToPublicKey(bin2hex($privateKeyBytes));
    } else {
        throw new Exception('Unrecognized private key format');
    }

    $allAddresses = [];

    if (in_array($coinUpper, ['ETH', 'ETC', 'USDT', 'POLYGON', 'BSC'])) {
        $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::privateKeyToEthereumAddress($privateKey)];
    } elseif ($coinUpper === 'BCH') {
        $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::publicKeyToBitcoinCashAddress($pubkeyHex)];
    } elseif ($coinUpper === 'KASPA') {
        $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Wallet::kaspaAddressFromPublicKey($pubkeyHex)];
    } elseif ($coinUpper === 'XRP') {
        $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, Address::xrpAddress($pubkeyHex)];
    } else {
        $allAddresses[] = [$walletId, $coinUpper, 'P2PKH', 0, $importBackupP2PKH($pubkeyHex, $coinUpper)];
    }
    if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'BTG', 'RVN', 'DGB'])) {
        $allAddresses[] = [$walletId, $coinUpper, 'P2SH', 0, $importBackupP2SH($pubkeyHex, $coinUpper)];
    }
    if (in_array($coinUpper, ['BTC', 'BTCT', 'LTC', 'DGB', 'BTG', 'RVN'])) {
        $allAddresses[] = [$walletId, $coinUpper, 'P2WPKH', 0, $importBackupP2WPKH($pubkeyHex, $coinUpper)];
    }

    // Non-custodial: import addresses from the backup file; never persist keys or PINs.
    $passwordHash = Wallet::hashPassword($password);
    $keyType = (strpos($coinUpper, 'xprv') === 0 || strpos($coinUpper, 'tprv') === 0) ? 'HD' : ($importBackupIsValidWif($privateKey) ? 'WIF' : 'HEX');
    $profile = json_encode(['default_coin' => $coinUpper, 'key_type' => $keyType]);

    Database::execute(
        'INSERT INTO wallets (id, password_hash, profile, id_coin) VALUES (?, ?, ?, ?)',
        [$walletId, $passwordHash, $profile, $coinUpper]
    );

    $placeholders = implode(',', array_fill(0, count($allAddresses), '(?, ?, ?, ?, ?)'));
    $flatParams = [];
    foreach ($allAddresses as $row) {
        foreach ($row as $val) {
            $flatParams[] = $val;
        }
    }

    Database::execute(
        'INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES ' . $placeholders,
        $flatParams
    );

    return ['walletId' => $walletId, 'alreadyExists' => false];
};

Api::requirePost();

try {
    $captchaToken = Api::body('captcha_token');
    $captchaCode = Api::body('captcha_code');
    $captchaResult = Captcha::verify($captchaToken, $captchaCode);
    if (!$captchaResult['valid']) {
        jsonResponse(['error' => $captchaResult['error']], 400);
    }

    $backup = Api::body('backup');
    $backupPassword = Api::body('backupPassword');
    if (!$backup || !$backupPassword) {
        jsonResponse(['error' => 'Please select a backup file and enter the backup password.'], 400);
    }

    try {
        $backupDataStr = Encryption::decrypt([
            'ciphertext' => $backup['ciphertext'] ?? null,
            'salt' => $backup['salt'] ?? null,
            'iv' => $backup['iv'] ?? null,
            'tag' => $backup['tag'] ?? null,
        ], $backupPassword);
    } catch (Throwable $e) {
        jsonResponse(['error' => 'Wrong backup password. Please check your password and try again.'], 401);
    }

    try {
        $parsed = json_decode($backupDataStr, true);
        if (!is_array($parsed)) {
            throw new Exception('Invalid backup JSON');
        }
        $backupData = $parsed;
    } catch (Throwable $e) {
        jsonResponse(['error' => 'Backup file is corrupted or not a valid PruneBit backup.'], 400);
    }

    $seed = $backupData['seed'] ?? null;
    $walletId = $backupData['walletId'] ?? null;
    $idCoin = $backupData['idCoin'] ?? null;
    $addresses = $backupData['addresses'] ?? null;

    $password = $importBackupIsTruthy($backupData['password'] ?? null) ? $backupData['password'] : $backupPassword;

    if (!$seed || !$walletId) {
        jsonResponse(['error' => 'Backup file is incomplete or missing required data.'], 400);
    }

    $existingWallets = Database::query('SELECT id, password_hash FROM wallets WHERE id = ?', [$walletId]);

    if (count($existingWallets) > 0) {
        $wallet = $existingWallets[0];
        if (!Wallet::verifyPassword($password, $wallet['password_hash'])) {
            jsonResponse(['error' => 'This wallet already exists but the PIN does not match. Enter the correct wallet PIN, or log in with your wallet PIN instead.'], 401);
        }
        $token = Session::create($walletId, 24, Session::metaFromRequest());
        jsonResponse(['success' => true, 'token' => $token, 'walletId' => $walletId, 'alreadyExists' => true]);
    }

    $words = preg_split('/\s+/', trim($seed));
    $isMnemonic = count($words) >= 12 && count($words) <= 24 && Bip39::validateMnemonic(trim($seed));

    $resultWalletId = null;

    if ($isMnemonic) {
        $result = Wallet::import(trim($seed), $password, [], false);
        $resultWalletId = $result['id'];
    } elseif ($importBackupIsValidHexPrivateKey($seed) || $importBackupIsValidWif($seed)) {
        $coin = $idCoin ?: 'BTC';
        $result = $importBackupImportPrivateKeyWallet($seed, $coin, $password);
        $resultWalletId = $result['walletId'];
    } else {
        jsonResponse(['error' => 'Unrecognized seed format in backup'], 400);
    }

    $token = Session::create($resultWalletId, 24, Session::metaFromRequest());
    jsonResponse(['success' => true, 'token' => $token, 'walletId' => $resultWalletId, 'alreadyExists' => false]);
} catch (Throwable $e) {
    error_log('Import backup error: ' . ($e->getCode() ? $e->getCode() : 'backup_import_failed'));
    jsonResponse(['error' => 'Restore failed'], 500);
}
