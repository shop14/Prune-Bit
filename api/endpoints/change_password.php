<?php

Api::requirePost();

try {
    $token = Api::body('token');
    $oldPassword = Api::body('oldPassword');
    $newPassword = Api::body('newPassword');

    if (!$token || !$oldPassword || !$newPassword) {
        jsonResponse(['error' => 'Token, old PIN, and new PIN required'], 400);
    }

    $wallet = Wallet::getByToken($token);

    if (!$wallet) {
        jsonResponse(['error' => 'Wallet not found'], 404);
    }

    $passwordMatch = Wallet::verifyPassword($oldPassword, $wallet['password_hash']);

    if (!$passwordMatch) {
        jsonResponse(['error' => 'Invalid old PIN'], 401);
    }

    // Non-custodial build: only the PIN hash is updated. No seed is stored,
    // so there is nothing to re-encrypt server-side.
    $newPasswordHash = Wallet::hashPassword($newPassword);

    Database::execute(
        'UPDATE wallets SET password_hash = ? WHERE id = ?',
        [$newPasswordHash, $wallet['id']]
    );

    Database::execute('DELETE FROM sessions WHERE wallet_id = ?', [$wallet['id']]);

    jsonResponse(['success' => true, 'message' => 'PIN changed successfully', 'backup' => null]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Invalid or expired session') !== false) {
        jsonResponse(['error' => 'Invalid or expired session'], 401);
    }
    if (strpos($msg, 'Wallet not found') !== false) {
        jsonResponse(['error' => 'Wallet not found'], 404);
    }
    error_log('change_password error: ' . $msg);
    jsonResponse(['error' => 'Internal server error'], 500);
}
