<?php

Api::requirePost();

try {
    $token = Api::body('token');
    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }
    $wallet = Wallet::getByToken($token);

    $ethRows = Database::query(
        'SELECT * FROM wallet_addresses WHERE wallet_id = ? AND coin = ? AND address_type = ? ORDER BY address_index',
        [$wallet['id'], 'ETH', 'P2PKH']
    );
    $existingUsdt = Database::query(
        'SELECT COUNT(*) as cnt FROM wallet_addresses WHERE wallet_id = ? AND coin = ?',
        [$wallet['id'], 'USDT']
    );
    if (count($ethRows) > 0 && ((int) ($existingUsdt[0]['cnt'] ?? 0)) === 0) {
        foreach ($ethRows as $ea) {
            Database::execute(
                'INSERT IGNORE INTO wallet_addresses (wallet_id, coin, address_type, address_index, address) VALUES (?, ?, ?, ?, ?)',
                [$wallet['id'], 'USDT', 'P2PKH', $ea['address_index'], $ea['address']]
            );
        }
    }

    $addresses = Database::query(
        'SELECT coin, address_type, address, address_index, balance, unconfirmed_balance, last_synced FROM wallet_addresses WHERE wallet_id = ? ORDER BY coin, FIELD(address_type, "P2WPKH", "BECH32", "P2SH", "P2PKH"), address_index',
        [$wallet['id']]
    );

    $result = [];
    foreach ($addresses as $a) {
        $result[] = [
            'coin' => $a['coin'],
            'type' => $a['address_type'] === 'BECH32' ? 'P2WPKH' : $a['address_type'],
            'address' => $a['address'],
            'index' => $a['address_index'],
            'balance' => (float) ($a['balance'] ?? 0),
            'unconfirmed' => (float) ($a['unconfirmed_balance'] ?? 0),
            'last_synced' => $a['last_synced']
        ];
    }

    $hasMnemonic = strpos($wallet['id'], 'wallet_pk_') !== 0;

    jsonResponse(['success' => true, 'hasMnemonic' => $hasMnemonic, 'addresses' => $result]);
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Session expired'], 401);
    }
    jsonResponse(['error' => 'Internal server error'], 500);
}
