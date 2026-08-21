<?php

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

function initTables() {
    try {
        Database::execute(
            'CREATE TABLE IF NOT EXISTS system_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                status VARCHAR(50) NOT NULL DEFAULT "operational",
                message VARCHAR(255) NOT NULL DEFAULT "All systems operational",
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        Database::execute(
            'CREATE TABLE IF NOT EXISTS incidents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                resolved TINYINT(1) DEFAULT 0,
                resolved_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        Database::execute(
            'CREATE TABLE IF NOT EXISTS admin_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255) NOT NULL UNIQUE,
                username VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $rows = Database::query('SELECT COUNT(*) AS count FROM system_status');
        if ((int) $rows[0]['count'] === 0) {
            Database::execute("INSERT INTO system_status (status, message) VALUES ('operational', 'All systems operational')");
        }
    } catch (Throwable $e) {
        error_log('Init tables error: ' . $e->getMessage());
    }
}

function verifyAdminToken($adminToken) {
    if (!$adminToken) return false;
    $tokenHash = hash('sha256', $adminToken);
    $rows = Database::query('SELECT * FROM admin_sessions WHERE token = ? AND expires_at > NOW()', [$tokenHash]);
    return count($rows) > 0;
}
