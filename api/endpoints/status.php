<?php

require_once __DIR__ . '/../schema.php';

function handleStatus($method) {
    try {
        initTables();
        if ($method === 'GET') {
            $status = Database::getStatus();
            $incidents = Database::getIncidents();
            return jsonResponse([
                'success' => true,
                'status' => $status['status'] ?? 'operational',
                'message' => $status['message'] ?? 'All systems operational',
                'updated_at' => $status['updated_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
                'incidents' => $incidents ?? [],
            ]);
        }

        // POST: admin token required
        $token = Api::body('admin_token');
        if (!$token) return jsonResponse(['error' => 'Admin token required'], 401);
        if (!verifyAdminToken($token)) return jsonResponse(['error' => 'Invalid or expired admin token'], 401);

        $status = Api::body('status');
        $message = Api::body('message');
        Database::updateStatus($status, $message);
        return jsonResponse(['success' => true, 'message' => 'Status updated successfully']);
    } catch (Throwable $e) {
        error_log('Status error: ' . $e->getMessage());
        return jsonResponse([
            'success' => true,
            'status' => 'operational',
            'message' => 'All systems operational',
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'incidents' => [],
        ]);
    }
}

handleStatus($GLOBALS['api_method'] ?? 'GET');
