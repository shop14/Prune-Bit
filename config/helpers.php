<?php

require_once __DIR__ . '/config.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function readJsonBody() {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function httpRequest($url, $method = 'GET', $body = null, $headers = [], $timeout = 8) {
    $url = blockcypherTokenUrl($url);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (PruneBit/1.0)',
    ]);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
            if (!is_string($body) || json_decode($body) !== null) {
                $headers[] = 'Content-Type: application/json';
            }
        }
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return null;
    }
    return ['status' => $status, 'body' => $raw, 'json' => json_decode($raw, true)];
}

function blockcypherTokenUrl($url) {
    if (stripos($url, 'https://api.blockcypher.com/') !== 0) {
        return $url;
    }
    static $tokens = null;
    if ($tokens === null) {
        $tokens = array_values(array_filter([
            env('BLOCKCYPHER_API_KEY_1'),
            env('BLOCKCYPHER_API_KEY_2'),
            env('BLOCKCYPHER_API_KEY_3'),
        ]));
        if ($tokens === []) {
            $tokens = [
                '86f86e88c6644fd09560ae63e83c43b7',
                '94508104107b4a7295357f8a0a456955',
                'f34e729707434418aab10b614d1c2da6',
            ];
        }
    }
    static $i = 0;
    $key = $tokens[$i % count($tokens)];
    $i++;
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $sep . 'token=' . rawurlencode($key);
}

function clientIp() {
    // By default, trust only REMOTE_ADDR. Set TRUSTED_PROXY_IPS to a comma-separated
    // list of CIDR ranges (e.g. '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16') to enable
    // reading X-Forwarded-For from those IPs only (e.g. for Cloudflare / reverse proxy).
    $trusted = env('TRUSTED_PROXY_IPS', '');
    $cidrs = array_filter(array_map('trim', explode(',', $trusted)));

    $forwarded = null;
    if ($cidrs !== []) {
        // Only consider X-Forwarded-For if the immediate peer (REMOTE_ADDR) is in the trusted list.
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        foreach ($cidrs as $cidr) {
            if (ipInCidr($remote, $cidr)) {
                $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
                break;
            }
        }
    }

    if ($forwarded !== null && $forwarded !== '') {
        $ip = trim(explode(',', $forwarded)[0]);
        if ($ip) return $ip;
    }

    // CF-Connecting-IP / X-Real-IP are only used if the request already passed the
    // trusted-proxy check above (same peer). If not behind a trusted proxy, fall through.
    if ($forwarded !== null && $forwarded !== '') {
        return trim(explode(',', $forwarded)[0]);
    }
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return trim($_SERVER['HTTP_X_REAL_IP']);

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ipInCidr($ip, $cidr) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    list($subnet, $mask) = explode('/', $cidr);
    $ipBits = sprintf('%0128s', decbin(ip2long($ip)));
    $mask = (int) $mask;
    $subnetBits = sprintf('%0128s', decbin(ip2long($subnet)));
    return substr($ipBits, 0, $mask) === substr($subnetBits, 0, $mask);
}

function utcNow($format = 'Y-m-d H:i:s') {
    return (new DateTime('now', new DateTimeZone('UTC')))->format($format);
}

function jsonEncodeUtf8($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

function apiToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '');
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }
    if (isset($GLOBALS['api_body']['token'])) {
        return $GLOBALS['api_body']['token'];
    }
    return null;
}

function requireWalletAuth() {
    $token = apiToken();
    if (!$token) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    try {
        return Wallet::getByToken($token);
    } catch (Throwable $e) {
        jsonResponse(['error' => $e->getMessage()], 401);
    }
    return null;
}

function walletAdminPasswordHash() {
    $rows = Database::query("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash'");
    return count($rows) > 0 ? $rows[0]['setting_value'] : null;
}

function requireAdminToken() {
    $token = Api::body('token');
    if (!$token) {
        jsonResponse(['error' => 'Admin token required'], 401);
    }
    $tokenHash = hash('sha256', $token);
    $rows = Database::query('SELECT * FROM admin_sessions WHERE token = ? AND expires_at > NOW()', [$tokenHash]);
    if (count($rows) === 0) {
        error_log('requireAdminToken: No session found for token hash ' . $tokenHash);
        jsonResponse(['error' => 'Invalid or expired admin token'], 401);
    }
    // Inactivity timeout: 30 minutes (match admin panel idle timeout)
    $inactivityMinutes = 30;
    if (!empty($rows[0]['last_activity'])) {
        // Parse MySQL datetime as UTC (MySQL session timezone is UTC)
        $lastActivityTs = (new DateTime($rows[0]['last_activity'], new DateTimeZone('UTC')))->getTimestamp();
        $nowTs = time();
        $diffMin = ($nowTs - $lastActivityTs) / 60;
        if ($diffMin > $inactivityMinutes) {
            Database::execute('DELETE FROM admin_sessions WHERE token = ?', [$tokenHash]);
            jsonResponse(['error' => 'Admin session expired due to inactivity'], 401);
        }
    }
    // Update last_activity
    Database::execute('UPDATE admin_sessions SET last_activity = NOW() WHERE token = ?', [$tokenHash]);
    return $rows[0];
}
