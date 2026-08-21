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

$pbCnWalletCoinMap = [
    'BTC' => ['ticker' => 'btc', 'network' => 'btc'],
    'ETH' => ['ticker' => 'eth', 'network' => 'eth'],
    'USDT' => ['ticker' => 'usdt', 'network' => 'eth'],
    'LTC' => ['ticker' => 'ltc', 'network' => 'ltc'],
    'DOGE' => ['ticker' => 'doge', 'network' => 'doge'],
    'BCH' => ['ticker' => 'bch', 'network' => 'bch'],
    'DGB' => ['ticker' => 'dgb', 'network' => 'dgb'],
    'RVN' => ['ticker' => 'rvn', 'network' => 'rvn'],
    'ZEC' => ['ticker' => 'zec', 'network' => 'zec'],
    'BSV' => ['ticker' => 'bsv', 'network' => 'bsv'],
    'XVG' => ['ticker' => 'xvg', 'network' => 'xvg'],
    'QTUM' => ['ticker' => 'qtum', 'network' => 'qtum'],
    'ETC' => ['ticker' => 'etc', 'network' => 'etc'],
    'KASPA' => ['ticker' => 'kas', 'network' => 'kas'],
    'XRP' => ['ticker' => 'xrp', 'network' => 'xrp'],
    'POLYGON' => ['ticker' => 'pol', 'network' => 'eth'],
    'BSC' => ['ticker' => 'bnb', 'network' => 'bsc'],
];

$pbCnAddressValidators = [
    'BTC' => '~^([13][a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-zA-HJ-NP-Z0-9]{25,90})$~',
    'ETH' => '~^0x[0-9a-fA-F]{40}$~',
    'USDT' => '~^0x[0-9a-fA-F]{40}$~',
    'POLYGON' => '~^0x[0-9a-fA-F]{40}$~',
    'BSC' => '~^0x[0-9a-fA-F]{40}$~',
    'ETC' => '~^0x[0-9a-fA-F]{40}$~',
    'LTC' => '~^[LM][a-km-zA-HJ-NP-Z1-9]{26,34}|ltc1[a-zA-HJ-NP-Z0-9]{25,90}$~',
    'DOGE' => '~^D[5-9A-HJ-NP-U][a-km-zA-HJ-NP-Z1-9]{25,34}$~',
    'DGB' => '~^[Ddag][a-km-zA-HJ-NP-Z1-9]{25,34}|dgb1[a-zA-HJ-NP-Z0-9]{25,90}$~',
    'BCH' => '~^(bitcoincash:)?[qQpP][a-km-zA-HJ-NP-Z1-9]{40,41}$~',
    'ZEC' => '~^t[a-km-zA-HJ-NP-Z1-9]{34}$~',
    'BSV' => '~^([13][a-km-zA-HJ-NP-Z1-9]{25,34})$~',
    'XVG' => '~^D[a-km-zA-HJ-NP-Z1-9]{25,34}$~',
    'QTUM' => '~^Q[a-km-zA-HJ-NP-Z1-9]{25,34}$~',
    'RVN' => '~^R[a-km-zA-HJ-NP-Z1-9]{25,34}$~',
    'KASPA' => '~^(kaspa:)?[a-zA-Z0-9]{61,63}$~',
    'XRP' => '~^r[a-zA-Z0-9]{24,34}$~',
];

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

$pbCnCoinMap = function ($coin) use ($pbCnWalletCoinMap) {
    $key = strtoupper((string) ($coin ?: ''));
    return isset($pbCnWalletCoinMap[$key]) ? $pbCnWalletCoinMap[$key] : null;
};

$pbCnValidateAddress = function ($coin, $address) use ($pbCnAddressValidators) {
    $key = strtoupper((string) ($coin ?: ''));
    $address = trim($address);
    if (!isset($pbCnAddressValidators[$key])) return mb_strlen($address) >= 10;
    return (bool) preg_match($pbCnAddressValidators[$key], $address);
};

$pbSegs = explode('/', strtolower(trim($GLOBALS['api_path'] ?? '', '/')));
$pbAction = (string) end($pbSegs);
$pbMethod = $GLOBALS['api_method'] ?? 'GET';

if ($pbAction === 'currencies') {
    if ($pbMethod !== 'GET') {
        jsonResponse(['error' => 'Not found'], 404);
    }
    try {
        $pbCnInitTables();
        $currenciesCache = &$GLOBALS['pb_cn_currencies_cache'];
        if (!isset($currenciesCache)) {
            $currenciesCache = ['data' => null, 'ts' => 0];
        }
        if ($currenciesCache['data'] !== null && (time() - $currenciesCache['ts']) < 6 * 60 * 60) {
            jsonResponse(['success' => true, 'currencies' => $currenciesCache['data']]);
        }

        $list = $pbCnFetch('/exchange/currencies?active=true&flow=standard');
        $result = [];
        foreach ($pbCnWalletCoinMap as $walletCoin => $map) {
            $match = null;
            foreach ($list as $c) {
                if (($c['ticker'] ?? null) === $map['ticker'] && ($c['network'] ?? null) === $map['network']) {
                    $match = $c;
                    break;
                }
            }
            if ($match) {
                $result[] = [
                    'walletCoin' => $walletCoin,
                    'ticker' => $match['ticker'],
                    'network' => $match['network'],
                    'name' => $match['name'],
                    'buy' => !empty($match['buy']),
                    'sell' => !empty($match['sell']),
                    'hasExternalId' => !empty($match['hasExternalId']),
                    'supportsFixedRate' => !empty($match['supportsFixedRate']),
                ];
            }
        }

        $currenciesCache = ['data' => $result, 'ts' => time()];
        jsonResponse(['success' => true, 'currencies' => $result]);
    } catch (PbChangnowError $e) {
        error_log('ChangeNOW currencies error: ' . ($e->codeStr ? $e->codeStr : $e->getMessage()));
        jsonResponse(['error' => $e->codeStr ? $e->codeStr : 'changenow_error', 'message' => $e->getMessage()], $e->status ? $e->status : 500);
    } catch (Throwable $e) {
        error_log('ChangeNOW currencies error: ' . $e->getMessage());
        jsonResponse(['error' => 'changenow_error', 'message' => 'Service temporarily unavailable'], 500);
    }
}

if ($pbAction === 'estimate') {
    if ($pbMethod !== 'POST') {
        jsonResponse(['error' => 'Not found'], 404);
    }
    try {
        $pbCnInitTables();
        $wallet = $pbCnRequireWallet();
        $from = $pbCnCoinMap(Api::body('from'));
        $to = $pbCnCoinMap(Api::body('to'));

        if (!$from || !$to) {
            jsonResponse(['error' => 'unsupported_coin', 'message' => 'Coin not supported for exchange'], 400);
        }
        if ((string) Api::body('from') === (string) Api::body('to')) {
            jsonResponse(['error' => 'same_coin', 'message' => 'Cannot exchange a coin into itself'], 400);
        }
        $amount = (float) Api::body('amount');
        if (is_nan($amount) || $amount <= 0) {
            jsonResponse(['error' => 'invalid_amount', 'message' => 'Invalid amount'], 400);
        }

        $apiPath = '/exchange/estimated-amount?fromCurrency=' . $from['ticker'] . '&toCurrency=' . $to['ticker'] . '&fromAmount=' . $amount . '&flow=standard&type=direct';
        $est = $pbCnFetch($apiPath);
        jsonResponse([
            'success' => true,
            'walletId' => $wallet['id'],
            'estimate' => [
                'from' => $est['fromCurrency'] ?? null,
                'to' => $est['toCurrency'] ?? null,
                'fromAmount' => $est['fromAmount'] ?? null,
                'toAmount' => $est['toAmount'] ?? null,
                'depositFee' => $est['depositFee'] ?? null,
                'withdrawalFee' => $est['withdrawalFee'] ?? null,
                'transactionSpeedForecast' => $est['transactionSpeedForecast'] ?? null,
            ],
        ]);
    } catch (PbChangnowError $e) {
        if ($e->codeStr === 'deposit_too_small') {
            $resp = ['error' => $e->codeStr, 'message' => $e->getMessage()];
            if (is_array($e->payload) && array_key_exists('range', $e->payload)) {
                $resp['range'] = $e->payload['range'];
            }
            jsonResponse($resp, 400);
        }
        error_log('ChangeNOW estimate error: ' . ($e->codeStr ? $e->codeStr : $e->getMessage()));
        jsonResponse(['error' => $e->codeStr ? $e->codeStr : 'changenow_error', 'message' => $e->getMessage()], $e->status ? $e->status : 500);
    } catch (Throwable $e) {
        error_log('ChangeNOW estimate error: ' . $e->getMessage());
        jsonResponse(['error' => 'changenow_error', 'message' => 'Service temporarily unavailable'], 500);
    }
}

if ($pbAction === 'create') {
    if ($pbMethod !== 'POST') {
        jsonResponse(['error' => 'Not found'], 404);
    }
    try {
        $pbCnInitTables();
        $wallet = $pbCnRequireWallet();
        $from = $pbCnCoinMap(Api::body('from'));
        $to = $pbCnCoinMap(Api::body('to'));

        if (!$from || !$to) {
            jsonResponse(['error' => 'unsupported_coin', 'message' => 'Coin not supported for exchange'], 400);
        }
        if ((string) Api::body('from') === (string) Api::body('to')) {
            jsonResponse(['error' => 'same_coin', 'message' => 'Cannot exchange a coin into itself'], 400);
        }
        $amount = (float) Api::body('amount');
        if (is_nan($amount) || $amount <= 0) {
            jsonResponse(['error' => 'invalid_amount', 'message' => 'Invalid amount'], 400);
        }

        $address = trim((string) (Api::body('address') ?: ''));
        if (mb_strlen($address) < 10) {
            jsonResponse(['error' => 'invalid_address', 'message' => 'Invalid payout address'], 400);
        }

        $addressValid = $pbCnValidateAddress(Api::body('to'), $address);
        if (!$addressValid) {
            jsonResponse(['error' => 'invalid_address', 'message' => 'Invalid ' . strtoupper((string) (Api::body('to') ?: '')) . ' address format'], 400);
        }
        try {
            $validateResult = $pbCnFetch('/validate/address?currency=' . $to['ticker'] . '&address=' . rawurlencode($address));
            if (is_array($validateResult) && array_key_exists('result', $validateResult) && $validateResult['result'] === false) {
                jsonResponse(['error' => 'invalid_address', 'message' => 'Invalid ' . strtoupper((string) (Api::body('to') ?: '')) . ' address'], 400);
            }
        } catch (Throwable $e) {
        }

        $toCoinUpper = strtoupper((string) (Api::body('to') ?: ''));
        $extraId = trim((string) (Api::body('extraId') ?: ''));
        if ($toCoinUpper === 'XRP' && $extraId === '') {
            jsonResponse(['error' => 'extra_id_required', 'message' => 'XRP requires a destination tag (memo)'], 400);
        }
        if (mb_strlen($extraId) > 120) {
            jsonResponse(['error' => 'invalid_extra_id', 'message' => 'Destination tag is too long'], 400);
        }

        $payload = [
            'fromCurrency' => $from['ticker'],
            'toCurrency' => $to['ticker'],
            'fromNetwork' => $from['network'],
            'toNetwork' => $to['network'],
            'fromAmount' => (string) $amount,
            'toAmount' => '',
            'address' => $address,
            'extraId' => $extraId,
            'refundAddress' => (string) (Api::body('refundAddress') ?: ''),
            'refundExtraId' => '',
            'userId' => $wallet['id'],
            'payload' => 'PruneBit Exchange',
            'contactEmail' => (string) (Api::body('contactEmail') ?: ''),
            'source' => 'PruneBit',
            'flow' => 'standard',
            'type' => 'direct',
            'rateId' => '',
        ];

        $tx = $pbCnFetch('/exchange', 'POST', json_encode($payload));

        $payinExtra = $pbCnPick($tx, 'payinExtraId', $pbCnPick($tx, 'payinExtra', null));
        $toAmount = (float) ($tx['toAmount'] ?? 0) ?: 0;
        $rateId = $pbCnPick($tx, 'rateId', null);

        Database::execute(
            'INSERT INTO changenow_exchanges (wallet_id, exchange_id, from_currency, to_currency, from_amount, to_amount, payout_address, payin_address, payin_extra_id, status, rate_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$wallet['id'], $tx['id'], $pbCnPick($tx, 'fromCurrency', $from['ticker']), $pbCnPick($tx, 'toCurrency', $to['ticker']), $amount, $toAmount, $address, $pbCnPick($tx, 'payinAddress', null), $payinExtra, $pbCnPick($tx, 'status', 'new'), $rateId]
        );

        $createdAt = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
        jsonResponse([
            'success' => true,
            'exchange' => [
                'exchangeId' => $tx['id'],
                'from' => $tx['fromCurrency'] ?? null,
                'to' => $tx['toCurrency'] ?? null,
                'fromAmount' => $pbCnPick($tx, 'amountSend', (string) $amount),
                'toAmount' => $pbCnPick($tx, 'amountReceive', $toAmount),
                'payinAddress' => $pbCnPick($tx, 'payinAddress', null),
                'payinExtraId' => $payinExtra,
                'payoutAddress' => $address,
                'status' => $pbCnPick($tx, 'status', 'new'),
                'rateId' => $rateId,
                'createdAt' => $createdAt,
            ],
        ]);
    } catch (PbChangnowError $e) {
        error_log('ChangeNOW create error: ' . ($e->codeStr ? $e->codeStr : $e->getMessage()));
        jsonResponse(['error' => $e->codeStr ? $e->codeStr : 'changenow_error', 'message' => $e->getMessage()], $e->status ? $e->status : 500);
    } catch (Throwable $e) {
        error_log('ChangeNOW create error: ' . $e->getMessage());
        jsonResponse(['error' => 'changenow_error', 'message' => 'Service temporarily unavailable'], 500);
    }
}

if ($pbAction === 'range') {
    if ($pbMethod !== 'GET') {
        jsonResponse(['error' => 'Not found'], 404);
    }
    try {
        $pbCnInitTables();
        $wallet = $pbCnRequireWallet();
        $from = $pbCnCoinMap(isset($_GET['from']) ? $_GET['from'] : null);
        $to = $pbCnCoinMap(isset($_GET['to']) ? $_GET['to'] : null);
        if (!$from || !$to) {
            jsonResponse(['error' => 'unsupported_coin', 'message' => 'Coin not supported for exchange'], 400);
        }
        $apiPath = '/exchange/range?flow=standard&fromCurrency=' . $from['ticker'] . '&toCurrency=' . $to['ticker'];
        $range = $pbCnFetch($apiPath);
        jsonResponse(['success' => true, 'walletId' => $wallet['id'], 'minAmount' => $range['minAmount'] ?? null, 'maxAmount' => $range['maxAmount'] ?? null]);
    } catch (PbChangnowError $e) {
        error_log('ChangeNOW range error: ' . ($e->codeStr ? $e->codeStr : $e->getMessage()));
        jsonResponse(['error' => $e->codeStr ? $e->codeStr : 'changenow_error', 'message' => $e->getMessage()], $e->status ? $e->status : 500);
    } catch (Throwable $e) {
        error_log('ChangeNOW range error: ' . $e->getMessage());
        jsonResponse(['error' => 'changenow_error', 'message' => 'Service temporarily unavailable'], 500);
    }
}

if ($pbAction === 'list') {
    if ($pbMethod !== 'GET') {
        jsonResponse(['error' => 'Not found'], 404);
    }
    try {
        $pbCnInitTables();
        $wallet = $pbCnRequireWallet();
        $rows = Database::query(
            'SELECT id, exchange_id, from_currency, to_currency, from_amount, to_amount, payout_address, payin_address, payin_extra_id, status, created_at, updated_at FROM changenow_exchanges WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 20',
            [$wallet['id']]
        );
        jsonResponse(['success' => true, 'exchanges' => $rows]);
    } catch (PbChangnowError $e) {
        error_log('ChangeNOW list error: ' . ($e->codeStr ? $e->codeStr : $e->getMessage()));
        jsonResponse(['error' => $e->codeStr ? $e->codeStr : 'changenow_error', 'message' => $e->getMessage()], $e->status ? $e->status : 500);
    } catch (Throwable $e) {
        error_log('ChangeNOW list error: ' . $e->getMessage());
        jsonResponse(['error' => 'changenow_error', 'message' => 'Service temporarily unavailable'], 500);
    }
}

jsonResponse(['error' => 'Not found'], 404);
