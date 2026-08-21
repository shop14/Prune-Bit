<?php

/**
 * Environment/config loader.
 * Reads values in order: process env vars -> secret/.env file -> defaults.
 * Supports encrypted .env files (AES-256-GCM) with header "#ENCRYPTED:v1#".
 * Decryption key must be provided via ENV_ENCRYPTION_KEY environment variable.
 */
if (!function_exists('env')) {
function env($key, $default = null) {
    static $loaded = null;
    if ($loaded === null) {
        $loaded = [];
        $paths = [
            getenv('ENV_FILE') ?: '',
            __DIR__ . '/../secret/.env',
            __DIR__ . '/../../secret/.env',
        ];
        foreach ($paths as $p) {
            if ($p && is_file($p)) {
                $content = file_get_contents($p);
                if ($content !== false && str_starts_with(trim($content), '#ENCRYPTED:v1#')) {
                    $content = decryptEnvFile($content);
                }
                if ($content !== false && $content !== '') {
                    foreach (explode("\n", $content) as $line) {
                        $line = trim($line);
                        if ($line === '' || $line[0] === '#') continue;
                        $eq = strpos($line, '=');
                        if ($eq === false) continue;
                        $k = trim(substr($line, 0, $eq));
                        $v = trim(substr($line, $eq + 1));
                        $loaded[$k] = $v;
                    }
                }
            }
        }
        // Load config/api_keys.php (plain-text, no encryption, for shared hosting)
        $akPath = __DIR__ . '/api_keys.php';
        if (is_file($akPath)) {
            $akData = include $akPath;
            if (is_array($akData)) {
                foreach ($akData as $ak => $av) {
                    if (!isset($loaded[$ak]) || $loaded[$ak] === '') {
                        $loaded[$ak] = (string) $av;
                    }
                }
            }
        }
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($loaded[$key]) && $loaded[$key] !== '') return $loaded[$key];
    return $default;
}

function envEncryptionKey() {
    $key = getenv('ENV_ENCRYPTION_KEY');
    if ($key !== false && $key !== '') return $key;
    if (isset($_SERVER['ENV_ENCRYPTION_KEY']) && $_SERVER['ENV_ENCRYPTION_KEY'] !== '') return $_SERVER['ENV_ENCRYPTION_KEY'];
    static $keyFile = null;
    if ($keyFile === null) {
        $keyFile = '';
        foreach ([__DIR__ . '/../secret/.env_key', __DIR__ . '/../../secret/.env_key'] as $path) {
            if (is_file($path)) {
                $content = trim((string)file_get_contents($path));
                if ($content !== '') { $keyFile = $content; break; }
            }
        }
    }
    return $keyFile;
}

function decryptEnvFile(string $encryptedContent): string|false {
    $jsonPart = substr(trim($encryptedContent), strlen('#ENCRYPTED:v1#'));
    $data = json_decode($jsonPart, true);
    if (!$data || !isset($data['ciphertext'], $data['salt'], $data['iv'], $data['tag'])) {
        return false;
    }
    $key = envEncryptionKey();
    if ($key === '') {
        // Key not available — cannot decrypt. Return empty to fall back to defaults.
        return '';
    }
    try {
        require_once __DIR__ . '/../classes/Encryption.php';
        $plaintext = Encryption::decrypt([
            'ciphertext' => $data['ciphertext'],
            'salt' => $data['salt'],
            'iv' => $data['iv'],
            'tag' => $data['tag'],
        ], $key);
        return $plaintext;
    } catch (Throwable $e) {
        error_log('ENV decryption failed: ' . $e->getMessage());
        return false;
    }
}

function config($key, $default = null) {
    static $config = null;
    if ($config === null) {
        $config = include __DIR__ . '/constants.php';
    }
    if (array_key_exists($key, $config)) return $config[$key];
    return $default;
}

function dbConfig() {
    return [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'user' => env('DB_USER', ''),
        'password' => env('DB_PASSWORD', ''),
        'name' => env('DB_NAME', ''),
    ];
}
}
