<?php

Api::requirePost();

try {
    $mnemonic = Api::body('mnemonic');
    $password = Api::body('password');
    $walletId = Api::body('wallet_id');

    if (!$password) {
        jsonResponse(['error' => 'PIN required'], 400);
    }

    if (!$mnemonic && !$walletId) {
        jsonResponse(['error' => 'Mnemonic or wallet ID required'], 400);
    }

    $id = null;
    if ($walletId) {
        $id = $walletId;
    } else {
        $words = preg_split('/\s+/', trim($mnemonic));
        if (!in_array(count($words), [12, 15, 18, 21, 24])) {
            jsonResponse(['error' => 'Invalid mnemonic format. Must be 12, 15, 18, 21, or 24 words.'], 400);
        }
        $id = Encryption::hashMnemonic($mnemonic);
    }

    $result = Wallet::unlock($id, $password, Session::metaFromRequest());
    Wallet::repairEthAddresses($id, $result['mnemonic'], $password);
    Wallet::repairBchAddresses($id, $result['mnemonic'], $password);

    jsonResponse(['success' => true, 'token' => $result['token'], 'walletId' => $id]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Invalid PIN') !== false) {
        BanList::ban(clientIp());
        jsonResponse(['error' => 'Invalid PIN'], 403);
    }
    if (strpos($msg, 'Wallet not found') !== false) {
        jsonResponse(['error' => 'Wallet not found'], 404);
    }
    error_log('unlock error: ' . $msg);
    jsonResponse(['error' => 'Internal server error'], 500);
}
