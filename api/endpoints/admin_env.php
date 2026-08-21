<?php

Api::requirePost();
requireAdminToken();

$action = Api::body('action');

try {
    if ($action === 'status') {
        // Check if .env exists and is encrypted
        $envPath = __DIR__ . '/../../secret/.env';
        $exists = is_file($envPath);
        $encrypted = false;
        if ($exists) {
            $content = file_get_contents($envPath);
            $encrypted = $content !== false && str_starts_with(trim($content), '#ENCRYPTED:v1#');
        }
        jsonResponse([
            'success' => true,
            'exists' => $exists,
            'encrypted' => $encrypted,
            'env_key_set' => envEncryptionKey() !== ''
        ]);
    } elseif ($action === 'decrypt') {
        // Decrypt and return plaintext (admin only, requires valid session)
        $envPath = __DIR__ . '/../../secret/.env';
        if (!is_file($envPath)) {
            jsonResponse(['error' => '.env file not found'], 404);
        }
        $content = file_get_contents($envPath);
        if ($content === false || !str_starts_with(trim($content), '#ENCRYPTED:v1#')) {
            jsonResponse(['error' => '.env is not encrypted or unreadable'], 400);
        }
        $key = envEncryptionKey();
        if ($key === '') {
            jsonResponse(['error' => 'ENV_ENCRYPTION_KEY not set (set server env var or create secret/.env_key)'], 500);
        }
        $jsonPart = substr(trim($content), 14); // strlen('#ENCRYPTED:v1#') = 14
        $data = json_decode($jsonPart, true);
        if (!$data || !isset($data['ciphertext'], $data['salt'], $data['iv'], $data['tag'])) {
            jsonResponse(['error' => 'Invalid encrypted format'], 500);
        }
        require_once __DIR__ . '/../../classes/Encryption.php';
        try {
            $plaintext = Encryption::decrypt([
                'ciphertext' => $data['ciphertext'],
                'salt' => $data['salt'],
                'iv' => $data['iv'],
                'tag' => $data['tag'],
            ], $key);
            jsonResponse(['success' => true, 'plaintext' => $plaintext]);
        } catch (Throwable $e) {
            jsonResponse(['error' => 'Decryption failed'], 500);
        }
    } elseif ($action === 'encrypt') {
        // Encrypt plaintext and save
        $plaintext = Api::body('plaintext');
        if ($plaintext === null || $plaintext === '') {
            jsonResponse(['error' => 'Plaintext content required'], 400);
        }
        $key = envEncryptionKey();
        if ($key === '') {
            jsonResponse(['error' => 'ENV_ENCRYPTION_KEY not set (set server env var or create secret/.env_key)'], 500);
        }
        require_once __DIR__ . '/../../classes/Encryption.php';
        try {
            $encrypted = Encryption::encrypt($plaintext, $key);
            $payload = [
                'v' => 1,
                'ciphertext' => $encrypted['ciphertext'],
                'salt' => $encrypted['salt'],
                'iv' => $encrypted['iv'],
                'tag' => $encrypted['tag'],
            ];
            $output = '#ENCRYPTED:v1#' . json_encode($payload, JSON_UNESCAPED_SLASHES);
            $envPath = __DIR__ . '/../../secret/.env';
            $written = file_put_contents($envPath, $output);
            if ($written === false) {
                jsonResponse(['error' => 'Failed to write .env file (check permissions)'], 500);
            }
            jsonResponse(['success' => true, 'message' => '.env encrypted and saved']);
        } catch (Throwable $e) {
            jsonResponse(['error' => 'Encryption failed: ' . $e->getMessage()], 500);
        }
    } elseif ($action === 'encrypt_new') {
        // Create new .env from key-value pairs
        $vars = Api::body('vars'); // array of {key, value}
        if (!is_array($vars)) {
            jsonResponse(['error' => 'vars array required'], 400);
        }
        $lines = [];
        foreach ($vars as $v) {
            $k = trim((string)($v['key'] ?? ''));
            $val = (string)($v['value'] ?? '');
            if ($k !== '') {
                $lines[] = $k . '=' . $val;
            }
        }
        $plaintext = implode("\n", $lines);
        $key = envEncryptionKey();
        if ($key === '') {
            jsonResponse(['error' => 'ENV_ENCRYPTION_KEY not set (set server env var or create secret/.env_key)'], 500);
        }
        require_once __DIR__ . '/../../classes/Encryption.php';
        try {
            $encrypted = Encryption::encrypt($plaintext, $key);
            $payload = [
                'v' => 1,
                'ciphertext' => $encrypted['ciphertext'],
                'salt' => $encrypted['salt'],
                'iv' => $encrypted['iv'],
                'tag' => $encrypted['tag'],
            ];
            $output = '#ENCRYPTED:v1#' . json_encode($payload, JSON_UNESCAPED_SLASHES);
            $envPath = __DIR__ . '/../../secret/.env';
            $written = file_put_contents($envPath, $output);
            if ($written === false) {
                jsonResponse(['error' => 'Failed to write .env file (check permissions)'], 500);
            }
            jsonResponse(['success' => true, 'message' => '.env created, encrypted and saved']);
        } catch (Throwable $e) {
            jsonResponse(['error' => 'Encryption failed: ' . $e->getMessage()], 500);
        }
    } elseif ($action === 'set_encryption_key') {
        // Save encryption key to secret/.env_key file
        $key = Api::body('key');
        if ($key === null || $key === '') {
            jsonResponse(['error' => 'Key required'], 400);
        }
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
            jsonResponse(['error' => 'Invalid key format. Must be 64 hex characters.'], 400);
        }
        $keyPath = __DIR__ . '/../../secret/.env_key';
        $written = file_put_contents($keyPath, $key);
        if ($written === false) {
            jsonResponse(['error' => 'Failed to write key file (check permissions)'], 500);
        }
        // Set restrictive permissions
        @chmod($keyPath, 0600);
        jsonResponse(['success' => true, 'message' => 'Encryption key saved']);
    } else {
        jsonResponse(['error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}