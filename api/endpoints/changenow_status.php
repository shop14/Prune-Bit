<?php

if (!class_exists('PbChangnowError', false)) {
    class PbChangnowError extends \Exception {
        public $status;
        public $codeStr;
        public $payload;

        public function __construct($message, $status = null, $codeStr = null, $payload = null) {
            parent::__construct($message);
            $this->status = $status;
            $this->codeStr = $codeStr;
            $this->payload = $payload;
        }
    }
}

$pbCnApiKey = env('CHANGENOW_API_KEY');
$pbCnApiBase = 'https://api.changenow.io/v2';

$pbCnInitTables = function () {
    Database::execute(
        'CREATE TABLE IF NOT EXISTS changenow_exchanges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wallet_id VARCHAR(160) NOT NULL,
            exchange_id VARCHAR(120) NOT NULL,
            from_currency VARCHAR(20) NOT NULL,
            to_currency VARCHAR(20) NOT NULL,
            from_amount DECIMAL(24,8) DEFAULT 0,
            to_amount DECIMAL(24,8) DEFAULT 0,
            payout_address VARCHAR(200) DEFAULT NULL,
            payin_address VARCHAR(200) DEFAULT NULL,
            payin_extra_id VARCHAR(120) DEFAULT NULL,
            status VARCHAR(40) DEFAULT "new",
            rate_id VARCHAR(120) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_wallet (wallet_id),
            INDEX idx_exchange (exchange_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};

$pbCnFetch = function ($apiPath, $method = 'GET', $body = null) use ($pbCnApiBase, $pbCnApiKey) {
    $headers = ['x-changenow-api-key: ' . $pbCnApiKey, 'Content-Type: application/json'];
    $resp = httpRequest($pbCnApiBase . $apiPath, $method, $body, $headers, 15);
    if ($resp === null) {
        throw new \Exception('ChangeNOW request failed (network error)');
    }
    $data = $resp['json'];
    if (!is_array($data)) {
        $text = (string) $resp['body'];
        $data = $text !== '' ? ['message' => $text] : [];
    }
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        $msg = (isset($data['message']) && $data['message'] !== '') ? $data['message'] : ('ChangeNOW request failed (' . $resp['status'] . ')');
        throw new PbChangnowError($msg, $resp['status'], isset($data['error']) ? $data['error'] : 'changenow_error', isset($data['payload']) ? $data['payload'] : null);
    }
    return $data;
};

$pbCnPick = function ($arr, $key, $fallback) {
    $v = $arr[$key] ?? null;
    return $v ? $v : $fallback;
};

$pbCnRequireWallet = function () {
    $token = Api::body('token');
    if (!$token) {
        $token = isset($_GET['token']) ? $_GET['token'] : null;
    }
    if (!$token) {
        throw new PbChangnowError('Token required', 401, 'token_required');
    }
    return Wallet::getByToken($token);
};

if (($GLOBALS['api_method'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Not found'], 404);
}

try {
    $pbCnInitTables();
    $wallet = $pbCnRequireWallet();
    $exchangeId = trim((string) (Api::param('id') ?: ''));
    if ($exchangeId === '') {
        jsonResponse(['error' => 'invalid_exchange_id', 'message' => 'Exchange ID required'], 400);
    }

    $rows = Database::query('SELECT wallet_id FROM changenow_exchanges WHERE exchange_id = ?', [$exchangeId]);
    if (count($rows) === 0 || $rows[0]['wallet_id'] !== $wallet['id']) {
        jsonResponse(['error' => 'not_found', 'message' => 'Exchange not found'], 404);
    }

    $status = $pbCnFetch('/exchange/by-id?id=' . rawurlencode($exchangeId));
    Database::execute('UPDATE changenow_exchanges SET status = ? WHERE exchange_id = ?', [$pbCnPick($status, 'status', 'unknown'), $exchangeId]);

    $payload = [
        'success' => true,
        'exchangeId' => $exchangeId,
        'status' => $pbCnPick($status, 'status', 'unknown'),
        'payoutHash' => $pbCnPick($status, 'payoutHash', null),
        'payinAddress' => $pbCnPick($status, 'payinAddress', null),
        'payinExtraId' => $pbCnPick($status, 'payinExtraId', null),
    ];
    if (array_key_exists('amountFrom', $status)) $payload['amountFrom'] = $status['amountFrom'];
    if (array_key_exists('amountTo', $status)) $payload['amountTo'] = $status['amountTo'];
    if (array_key_exists('expectedAmountTo', $status)) $payload['expectedAmountTo'] = $status['expectedAmountTo'];
    jsonResponse($payload);
} catch (PbChangnowError $e) {
    error_log('ChangeNOW status error: ' . ($e->codeStr ? $e->codeStr : $e->getMessage()));
    jsonResponse(['error' => $e->codeStr ? $e->codeStr : 'changenow_error', 'message' => $e->getMessage()], $e->status ? $e->status : 500);
} catch (Throwable $e) {
    error_log('ChangeNOW status error: ' . $e->getMessage());
    jsonResponse(['error' => 'changenow_error', 'message' => 'Service temporarily unavailable'], 500);
}
