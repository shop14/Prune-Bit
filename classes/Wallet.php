<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Encryption.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Address.php';
require_once __DIR__ . '/Bip39.php';
require_once __DIR__ . '/BlockchainAPI.php';

class Wallet {
    private static $singleTypeCoins = ['BCH', 'ETH', 'DOGE', 'DASH', 'RVN', 'BTG', 'ETC', 'POLYGON', 'BSC', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD', 'KASPA', 'XRP'];

    public static function hashPassword($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        return str_replace('$2y$', '$2b$', $hash);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function create($mnemonic, $password, $profile = []) {
        // Non-custodial: the mnemonic is used in-memory to derive addresses and is never persisted.
        $id = Encryption::hashMnemonic($mnemonic);
        $passwordHash = self::hashPassword($password);

        try {
            Database::execute(
                'INSERT INTO wallets (id, password_hash, profile, id_coin)
                 VALUES (?, ?, ?, ?)',
                [$id, $passwordHash, json_encode($profile), 'BTC']
            );
            self::generateAndStoreAddresses($id, $mnemonic);
            return ['id' => $id, 'mnemonic' => $mnemonic];
        } catch (PDOException $e) {
            if (strpos(strtolower($e->getMessage()), 'duplicate') !== false) {
                throw new Exception('Wallet already exists');
            }
            throw $e;
        }
    }

    public static function import($mnemonic, $password, $profile = [], $forceNewId = false, $coinType = 'BTC') {
        // Non-custodial: derive and store addresses only; never persist the mnemonic or PIN.
        if ($forceNewId) {
            $id = 'wallet_' . time() . '_' . bin2hex(random_bytes(12));
        } else {
            $id = Encryption::hashMnemonic($mnemonic);
        }
        $passwordHash = self::hashPassword($password);

        Database::execute(
            'INSERT INTO wallets (id, password_hash, profile, id_coin)
             VALUES (?, ?, ?, ?)',
            [$id, $passwordHash, json_encode($profile), 'BTC']
        );

        self::generateAndStoreAddresses($id, $mnemonic);

        return ['id' => $id, 'mnemonic' => $mnemonic];
    }

    public static function importPrivateKey($privateKey, $password, $coinType = 'BTC', $profile = [], $meta = []) {
        try {
            if (strpos($privateKey, 'xprv') === 0 || strpos($privateKey, 'tprv') === 0) {
                $node = Base58::base58CheckDecode($privateKey);
                $seed = substr($node, 46, 32);
                $mnemonic = Bip39::entropyToMnemonic(bin2hex($seed));
            } elseif (strlen($privateKey) === 64 && preg_match('/^[0-9a-fA-F]+$/', $privateKey)) {
                $mnemonic = Bip39::entropyToMnemonic(strtolower($privateKey));
            } else {
                $decoded = Base58::base58CheckDecode($privateKey);
                $seed = substr($decoded, 1, 32);
                $mnemonic = Bip39::entropyToMnemonic(bin2hex($seed));
            }
        } catch (Throwable $e) {
            throw new Exception('Invalid private key format');
        }

        $walletId = Encryption::hashMnemonic($mnemonic);
        $existingWallets = Database::query('SELECT id, password_hash FROM wallets WHERE id = ?', [$walletId]);

        if (count($existingWallets) > 0) {
            $wallet = $existingWallets[0];
            if (!self::verifyPassword($password, $wallet['password_hash'])) {
                throw new Exception('Invalid PIN for this wallet. The private key exists but the PIN does not match.');
            }
            $token = Session::create($walletId, 24, $meta);
            self::repairEthAddresses($walletId, $mnemonic, $password);
            self::repairBchAddresses($walletId, $mnemonic, $password);
            return ['id' => $walletId, 'mnemonic' => $mnemonic, 'token' => $token, 'alreadyExists' => true];
        }

        return self::import($mnemonic, $password, $profile, false, $coinType);
    }

    public static function unlock($id, $password, $meta = []) {
        $wallets = Database::query('SELECT * FROM wallets WHERE id = ?', [$id]);
        if (count($wallets) === 0) {
            throw new Exception('Wallet not found');
        }
        $wallet = $wallets[0];
        if (!self::verifyPassword($password, $wallet['password_hash'])) {
            throw new Exception('Invalid PIN');
        }

        // Non-custodial: no stored seed to decrypt. The server only tracks
        // addresses and balances; the mnemonic never leaves the user.
        Database::execute('UPDATE wallets SET last_access = NOW() WHERE id = ?', [$id]);
        $token = Session::create($id, 24, $meta);

        return ['token' => $token, 'mnemonic' => null, 'wallet' => $wallet];
    }

    public static function getByToken($token) {
        $session = Session::validate($token);
        if (!$session) {
            throw new Exception('Invalid or expired session');
        }
        $rows = Database::query(
            'SELECT id, id_coin, total_balance, created_at, last_access, profile, password_hash FROM wallets WHERE id = ?',
            [$session['wallet_id']]
        );
        if (count($rows) === 0) {
            throw new Exception('Wallet not found');
        }
        return $rows[0];
    }

    public static function getById($id) {
        $rows = Database::query('SELECT id, id_coin, total_balance, created_at, last_access, profile FROM wallets WHERE id = ?', [$id]);
        return count($rows) > 0 ? $rows[0] : null;
    }

    public static function decryptMnemonic($wallet, $password) {
        // Non-custodial build: seeds are never stored, so they cannot be decrypted server-side.
        throw new Exception('Seed decryption is not available in the non-custodial edition');
    }

    public static function deriveAddress($wallet, $coin = 'BTC', $password = null) {
        // Read the stored address instead of deriving from a seed.
        $rows = Database::query(
            'SELECT address FROM wallet_addresses WHERE wallet_id = ? AND coin = ? ORDER BY address_index ASC LIMIT 1',
            [$wallet['id'], $coin]
        );
        if (count($rows) === 0) {
            throw new Exception('No stored address for this coin');
        }
        return $rows[0]['address'];
    }

    public static function deriveAddressByIndex($wallet, $coin = 'BTC', $password = null, $index = 0) {
        $rows = Database::query(
            'SELECT address FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_index = ? LIMIT 1',
            [$wallet['id'], $coin, $index]
        );
        if (count($rows) === 0) {
            throw new Exception('No stored address for this coin/index');
        }
        return $rows[0]['address'];
    }

    public static function generateAndStoreAddresses($walletId, $mnemonic) {
        $btcAddresses = Address::generateBitcoinStyle($mnemonic, 'BTC');
        for ($i = 0; $i < 3 && $i < count($btcAddresses['p2pkh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BTC', 'P2PKH', $i, $btcAddresses['p2pkh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($btcAddresses['p2sh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BTC', 'P2SH', $i, $btcAddresses['p2sh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($btcAddresses['bech32']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BTC', 'P2WPKH', $i, $btcAddresses['bech32'][$i]]);
        }

        $btctAddresses = Address::generateBitcoinStyle($mnemonic, 'BTCT');
        for ($i = 0; $i < 3 && $i < count($btctAddresses['p2pkh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BTCT', 'P2PKH', $i, $btctAddresses['p2pkh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($btctAddresses['p2sh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BTCT', 'P2SH', $i, $btctAddresses['p2sh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($btctAddresses['bech32']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BTCT', 'P2WPKH', $i, $btctAddresses['bech32'][$i]]);
        }

        $ltcAddresses = Address::generateBitcoinStyle($mnemonic, 'LTC');
        for ($i = 0; $i < 3 && $i < count($ltcAddresses['p2pkh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'LTC', 'P2PKH', $i, $ltcAddresses['p2pkh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($ltcAddresses['p2sh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'LTC', 'P2SH', $i, $ltcAddresses['p2sh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($ltcAddresses['bech32']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'LTC', 'P2WPKH', $i, $ltcAddresses['bech32'][$i]]);
        }

        $dgbAddresses = Address::generateBitcoinStyle($mnemonic, 'DGB');
        for ($i = 0; $i < 3 && $i < count($dgbAddresses['p2pkh']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'DGB', 'P2PKH', $i, $dgbAddresses['p2pkh'][$i]]);
        }
        for ($i = 0; $i < 3 && $i < count($dgbAddresses['bech32']); $i++) {
            Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'DGB', 'P2WPKH', $i, $dgbAddresses['bech32'][$i]]);
        }

        foreach (self::$singleTypeCoins as $coin) {
            for ($index = 0; $index < 3; $index++) {
                try {
                    $address = Address::deriveAddressByIndex($mnemonic, $coin, $index);
                    Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, $coin, 'P2PKH', $index, $address]);
                } catch (Throwable $e) {
                    error_log("Error generating address for {$coin} index {$index}: " . $e->getMessage());
                }
            }
        }

        $ethAddrs = Database::query('SELECT address, address_index FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_type = ? ORDER BY address_index', [$walletId, 'ETH', 'P2PKH']);
        foreach ($ethAddrs as $ea) {
            Database::execute('INSERT IGNORE INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'USDT', 'P2PKH', $ea['address_index'], $ea['address']]);
        }
    }

    public static function repairEthAddresses($walletId, $mnemonic, $password) {
        for ($index = 0; $index < 3; $index++) {
            $address = Address::deriveAddressByIndex($mnemonic, 'ETH', $index);
            $existing = Database::query(
                'SELECT id FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_type = ? AND address_index = ?',
                [$walletId, 'ETH', 'P2PKH', $index]
            );
            if (count($existing) > 0) {
                Database::execute('UPDATE wallet_addresses SET address = ? WHERE id = ?', [$address, $existing[0]['id']]);
            } else {
                Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'ETH', 'P2PKH', $index, $address]);
            }
        }
    }

    public static function generateMnemonic($strength = 128) {
        return Bip39::generateMnemonic($strength);
    }

    public static function getBalance($walletId, $coin = 'BTC', $password = null) {
        $wallet = self::getById($walletId);
        if (!$wallet) {
            throw new Exception('Wallet not found');
        }
        $address = self::deriveAddress($wallet, $coin);
        return BlockchainAPI::getBalance($address, $coin);
    }

    public static function getTransactions($walletId, $coin = 'BTC', $password = null) {
        $wallet = self::getById($walletId);
        if (!$wallet) {
            throw new Exception('Wallet not found');
        }
        $address = self::deriveAddress($wallet, $coin);
        return BlockchainAPI::getTransactions($address, $coin);
    }

    public static function broadcastTx($rawTxHex, $coin = 'BTC') {
        return BlockchainAPI::broadcastTx($rawTxHex, $coin);
    }

    public static function generateBTCAddresses($wallet, $password, $coin = 'BTC') {
        // Non-custodial build: cannot regenerate from a stored seed.
        throw new Exception('Not available in the non-custodial edition');
    }

    public static function privateKeyToEthereumAddress($privateKey) {
        return Address::privateKeyToEthereumAddress($privateKey);
    }

    public static function kaspaAddressFromPublicKey($pubkey) {
        return Address::kaspaAddress($pubkey);
    }

    public static function publicKeyToBitcoinCashAddress($publicKey) {
        return Address::bitcoinCashAddress($publicKey);
    }

    public static function repairBchAddresses($walletId, $mnemonic, $password) {
        for ($index = 0; $index < 3; $index++) {
            $address = Address::deriveAddressByIndex($mnemonic, 'BCH', $index);
            $existing = Database::query(
                'SELECT id FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_type = ? AND address_index = ?',
                [$walletId, 'BCH', 'P2PKH', $index]
            );
            if (count($existing) > 0) {
                Database::execute('UPDATE wallet_addresses SET address = ? WHERE id = ?', [$address, $existing[0]['id']]);
            } else {
                Database::execute('INSERT INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)', [$walletId, 'BCH', 'P2PKH', $index, $address]);
            }
        }
    }
}
