<?php

require_once __DIR__ . '/../config/database.php';

class Database {
    public static function execute($sql, $params = []) {
        try {
            $stmt = getPDO()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function query($sql, $params = []) {
        try {
            $stmt = getPDO()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Database query error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function getStatus() {
        try {
            $rows = self::query('SELECT status, message, updated_at FROM system_status ORDER BY id DESC LIMIT 1');
            if (count($rows) === 0) {
                return ['status' => 'operational', 'message' => 'All systems operational'];
            }
            return $rows[0];
        } catch (Throwable $e) {
            return ['status' => 'operational', 'message' => 'All systems operational'];
        }
    }

    public static function getIncidents() {
        try {
            return self::query('SELECT id, title, description, created_at, resolved, resolved_at FROM incidents WHERE resolved = 0 ORDER BY created_at DESC');
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function updateStatus($status, $message) {
        self::execute(
            'INSERT INTO system_status (status, message, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), updated_at = NOW()',
            [$status, $message]
        );
    }
}
