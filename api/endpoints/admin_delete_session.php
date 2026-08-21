<?php

Api::requirePost();
requireAdminToken();

try {
    $sessionToken = Api::body('session_token');
    if (!$sessionToken) {
        jsonResponse(['error' => 'Session token required'], 400);
    }
    $tokenHash = hash('sha256', $sessionToken);
    $exists = Database::query('SELECT token FROM sessions WHERE token = ?', [$tokenHash]);
    if (count($exists) === 0) {
        jsonResponse(['error' => 'Session not found'], 404);
    }
    Database::execute('DELETE FROM sessions WHERE token = ?', [$tokenHash]);
    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
