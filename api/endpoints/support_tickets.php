<?php

$pbInitTickets = function () {
    Database::execute(
        'CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL,
            wallet_id VARCHAR(160) DEFAULT NULL,
            category VARCHAR(60) NOT NULL,
            priority VARCHAR(40) NOT NULL DEFAULT "Normal",
            subject VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT "Open",
            ip_address VARCHAR(64) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};

$pbCleanText = function ($value, $maxLength) {
    return mb_substr(trim((string) ($value ?: '')), 0, $maxLength);
};

$pbSegs = explode('/', strtolower(trim($GLOBALS['api_path'] ?? '', '/')));
$pbAction = (string) end($pbSegs);

if ($pbAction === 'list') {
    Api::requirePost();
    requireAdminToken();
    try {
        $pbInitTickets();
        $tickets = Database::query('SELECT * FROM support_tickets ORDER BY created_at DESC LIMIT 200');
        jsonResponse(['success' => true, 'tickets' => $tickets]);
    } catch (Throwable $e) {
        error_log('Get support tickets error: ' . ($e->getCode() ? $e->getCode() : 'ticket_list_failed'));
        jsonResponse(['error' => 'Internal server error'], 500);
    }
}

if ($pbAction === 'resolve') {
    Api::requirePost();
    requireAdminToken();
    try {
        $ticketId = Api::body('ticket_id');
        if (!$ticketId) {
            jsonResponse(['error' => 'Ticket ID required'], 400);
        }

        Database::execute('UPDATE support_tickets SET status = ?, resolved_at = NOW() WHERE id = ?', ['Resolved', $ticketId]);
        jsonResponse(['success' => true, 'message' => 'Ticket resolved']);
    } catch (Throwable $e) {
        error_log('Resolve support ticket error: ' . ($e->getCode() ? $e->getCode() : 'ticket_resolve_failed'));
        jsonResponse(['error' => 'Internal server error'], 500);
    }
}

if ($pbAction === 'delete') {
    Api::requirePost();
    requireAdminToken();
    try {
        $ticketId = Api::body('ticket_id');
        if (!$ticketId) {
            jsonResponse(['error' => 'Ticket ID required'], 400);
        }

        Database::execute('DELETE FROM support_tickets WHERE id = ?', [$ticketId]);
        jsonResponse(['success' => true, 'message' => 'Ticket deleted']);
    } catch (Throwable $e) {
        error_log('Delete support ticket error: ' . ($e->getCode() ? $e->getCode() : 'ticket_delete_failed'));
        jsonResponse(['error' => 'Internal server error'], 500);
    }
}

Api::requirePost();

try {
    $pbInitTickets();

    $captchaResult = Captcha::verify(Api::body('captcha_token'), Api::body('captcha_code'));
    if (!$captchaResult['valid']) {
        jsonResponse(['error' => $captchaResult['error']], 400);
    }

    $name = $pbCleanText(Api::body('name'), 120);
    $email = $pbCleanText(Api::body('email'), 180);
    $walletId = $pbCleanText(Api::body('wallet_id'), 160);
    $category = $pbCleanText(Api::body('category'), 60);
    $priority = $pbCleanText(Api::body('priority') ?: 'Normal', 40);
    $subject = $pbCleanText(Api::body('subject'), 180);
    $message = $pbCleanText(Api::body('message'), 4000);
    $userAgent = $pbCleanText($_SERVER['HTTP_USER_AGENT'] ?? '', 1000);

    if (!$name || !preg_match('~^[^\s@]+@[^\s@]+\.[^\s@]+$~', $email) || !$category || !$subject || !$message) {
        jsonResponse(['error' => 'Name, valid email, category, subject, and message are required.'], 400);
    }

    Database::execute(
        'INSERT INTO support_tickets (name, email, wallet_id, category, priority, subject, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$name, $email, $walletId ?: null, $category, $priority, $subject, $message, clientIp(), $userAgent ?: null]
    );

    jsonResponse(['success' => true, 'message' => 'Ticket submitted successfully', 'ticket_id' => getPDO()->lastInsertId()], 201);
} catch (Throwable $e) {
    error_log('Submit support ticket error: ' . ($e->getCode() ? $e->getCode() : 'ticket_submit_failed'));
    jsonResponse(['error' => 'Failed to submit ticket.'], 500);
}
