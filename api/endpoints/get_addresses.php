<?php

Api::requirePost();

try {
    $token = Api::body('token');
    $coin = Api::body('coin');
    $addressType = Api::body('address_type');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 401);
    }

    $wallet = Wallet::getByToken($token);
    $resolvedWalletId = $wallet['id'];

    $upperCoin = $coin ? strtoupper($coin) : null;

    if ($upperCoin === 'USDT') {
        $ethRows = Database::query(
            'SELECT * FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_type = ? ORDER BY address_index',
            [$resolvedWalletId, 'ETH', 'P2PKH']
        );
        $existingUsdt = Database::query(
            'SELECT COUNT(*) as cnt FROM wallet_addresses WHERE wallet_id = ? AND coin = ?',
            [$resolvedWalletId, 'USDT']
        );
        if (count($ethRows) > 0 && ((int) ($existingUsdt[0]['cnt'] ?? 0)) === 0) {
            foreach ($ethRows as $ea) {
                Database::execute(
                    'INSERT IGNORE INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)',
                    [$resolvedWalletId, 'USDT', 'P2PKH', $ea['address_index'], $ea['address']]
                );
            }
        }
    }

    $sql = 'SELECT * FROM wallet_addresses WHERE wallet_id = ?';
    $params = [$resolvedWalletId];

    if ($coin) {
        $sql .= ' AND coin = ?';
        $params[] = $coin;
    }

    if ($addressType === 'P2WPKH' || $addressType === 'BECH32') {
        $sql .= ' AND address_type IN (?, ?)';
        $params[] = $addressType === 'P2WPKH' ? 'P2WPKH' : 'BECH32';
        $params[] = $addressType === 'P2WPKH' ? 'BECH32' : 'P2WPKH';
    } elseif ($addressType) {
        $sql .= ' AND address_type = ?';
        $params[] = $addressType;
    }

    $sql .= ' ORDER BY coin, CASE WHEN address_type = "P2WPKH" THEN 0 WHEN address_type = "BECH32" THEN 1 ELSE 2 END, address_index';

    $addresses = Database::query($sql, $params);

    $grouped = [];
    foreach ($addresses as $addr) {
        if (!isset($grouped[$addr['coin']])) {
            $grouped[$addr['coin']] = [];
        }
        $grouped[$addr['coin']][] = [
            'index' => $addr['address_index'],
            'type' => $addr['address_type'] === 'BECH32' ? 'P2WPKH' : $addr['address_type'],
            'address' => $addr['address'],
        ];
    }

    jsonResponse(['success' => true, 'addresses' => $grouped]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
