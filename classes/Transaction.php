<?php

require_once __DIR__ . '/Database.php';

class Transaction {
    public static function create($walletId, $coin, $txHash, $fromAddress, $toAddress, $amount, $fee, $confirmations = 0) {
        Database::execute(
            'INSERT INTO transactions (wallet_id, coin, tx_hash, from_address, to_address, amount, fee, confirmations) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$walletId, $coin, $txHash, $fromAddress, $toAddress, $amount, $fee, $confirmations]
        );
        return (int) getPDO()->lastInsertId();
    }

    public static function getByWallet($walletId, $coin = null) {
        $sql = 'SELECT * FROM transactions WHERE wallet_id = ?';
        $params = [$walletId];
        if ($coin) {
            $sql .= ' AND coin = ?';
            $params[] = $coin;
        }
        $sql .= ' ORDER BY created_at DESC';
        return Database::query($sql, $params);
    }

    public static function updateStatus($txId, $status) {
        Database::execute('UPDATE transactions SET status = ? WHERE id = ?', [$status, $txId]);
    }
}
