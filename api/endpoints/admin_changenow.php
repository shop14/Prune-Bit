<?php

Api::requirePost();
requireAdminToken();

$pbInitChangenow = function () {
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

$pbSegs = explode('/', strtolower(trim($GLOBALS['api_path'] ?? '', '/')));
$pbAction = (string) end($pbSegs);

if ($pbAction === 'list') {
    try {
        $pbInitChangenow();
        $status = Api::body('status');
        $page = Api::body('page', 1);
        $limit = Api::body('limit', 50);
        $pageInt = max(1, (int) $page);
        $limitInt = min(500, max(1, (int) $limit));
        $offset = ($pageInt - 1) * $limitInt;

        $where = '';
        $params = [];
        if ($status && $status !== 'all') {
            $where = 'WHERE status = ?';
            $params[] = $status;
        }

        $countResult = Database::query('SELECT COUNT(*) as total FROM changenow_exchanges ' . $where, $params);
        $total = $countResult[0]['total'];

        $exchanges = Database::query(
            'SELECT id, wallet_id, exchange_id, from_currency, to_currency, from_amount, to_amount, payout_address, payin_address, payin_extra_id, status, rate_id, created_at, updated_at FROM changenow_exchanges ' . $where . ' ORDER BY created_at DESC LIMIT ' . $limitInt . ' OFFSET ' . $offset,
            $params
        );

        jsonResponse(['success' => true, 'exchanges' => $exchanges, 'total' => (int) $total, 'page' => $pageInt, 'limit' => $limitInt]);
    } catch (Throwable $e) {
        error_log('Admin exchanges list error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch exchanges'], 500);
    }
}

if ($pbAction === 'update') {
    try {
        $pbInitChangenow();
        $exchangeId = Api::body('exchange_id');
        $status = Api::body('status');
        if (!$exchangeId) {
            jsonResponse(['error' => 'exchange_id is required'], 400);
        }

        $validStatuses = ['new', 'waiting', 'confirming', 'exchanging', 'sending', 'finished', 'failed', 'refunded', 'verifying', 'expired'];
        if ($status && !in_array($status, $validStatuses, true)) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }

        $existing = Database::query('SELECT id FROM changenow_exchanges WHERE exchange_id = ?', [$exchangeId]);
        if (count($existing) === 0) {
            jsonResponse(['error' => 'Exchange not found'], 404);
        }

        $body = Api::body();
        $updates = [];
        $params = [];
        if ($status) {
            $updates[] = 'status = ?';
            $params[] = $status;
        }
        if (array_key_exists('payout_address', $body)) {
            $updates[] = 'payout_address = ?';
            $params[] = $body['payout_address'];
        }
        if (array_key_exists('payin_address', $body)) {
            $updates[] = 'payin_address = ?';
            $params[] = $body['payin_address'];
        }

        if (count($updates) === 0) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $params[] = $exchangeId;
        Database::execute('UPDATE changenow_exchanges SET ' . implode(', ', $updates) . ' WHERE exchange_id = ?', $params);

        jsonResponse(['success' => true, 'message' => 'Exchange updated']);
    } catch (Throwable $e) {
        error_log('Admin exchange update error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to update exchange'], 500);
    }
}

if ($pbAction === 'delete') {
    try {
        $pbInitChangenow();
        $exchangeId = Api::body('exchange_id');
        if (!$exchangeId) {
            jsonResponse(['error' => 'exchange_id is required'], 400);
        }

        $existing = Database::query('SELECT id FROM changenow_exchanges WHERE exchange_id = ?', [$exchangeId]);
        if (count($existing) === 0) {
            jsonResponse(['error' => 'Exchange not found'], 404);
        }

        Database::execute('DELETE FROM changenow_exchanges WHERE exchange_id = ?', [$exchangeId]);
        jsonResponse(['success' => true, 'message' => 'Exchange deleted']);
    } catch (Throwable $e) {
        error_log('Admin exchange delete error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete exchange'], 500);
    }
}

jsonResponse(['error' => 'Not found'], 404);
