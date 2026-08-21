<?php

Api::requirePost();

try {
    $token = Api::body('token');

    if (!$token) {
        jsonResponse(['error' => 'Token required'], 400);
    }

    try {
        $wallet = Wallet::getByToken($token);
        $safeWallet = [];
        $safeWallet['id'] = $wallet['id'];
        $safeWallet['id_coin'] = $wallet['id_coin'];
        $safeWallet['total_balance'] = $wallet['total_balance'];
        $safeWallet['profile'] = $wallet['profile'];
        $safeWallet['created_at'] = $wallet['created_at'];
        if (array_key_exists('updated_at', $wallet)) {
            $safeWallet['updated_at'] = $wallet['updated_at'];
        }
        $safeWallet['last_access'] = $wallet['last_access'];
        jsonResponse(['success' => true, 'wallet' => $safeWallet]);
    } catch (Throwable $e) {
        if ($e->getMessage() === 'Invalid or expired session') {
            jsonResponse(['error' => 'Invalid or expired session'], 401);
        }
        throw $e;
    }
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
