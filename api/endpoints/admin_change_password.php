<?php

Api::requirePost();
requireAdminToken();

try {
    $currentPassword = Api::body('current_password');
    $newPassword = Api::body('new_password');

    if (!$currentPassword) {
        jsonResponse(['error' => 'Current admin password is required'], 400);
    }
    if (!$newPassword || strlen((string) $newPassword) < 12) {
        jsonResponse(['error' => 'New admin password must be at least 12 characters'], 400);
    }

    $settings = Database::query("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash'");
    if (count($settings) === 0) {
        jsonResponse(['error' => 'No admin account configured'], 401);
    }

    $currentValid = password_verify((string) $currentPassword, $settings[0]['setting_value']);
    if (!$currentValid) {
        jsonResponse(['error' => 'Invalid current password'], 401);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    Database::execute(
        "INSERT INTO admin_settings (setting_key, setting_value, updated_at) VALUES ('password_hash', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
        [$passwordHash]
    );
    jsonResponse(['success' => true, 'message' => 'Admin password changed successfully']);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
