<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';

class Api {
    private static $routes = [
        'status' => 'status.php',
        'incidents' => 'incidents.php',
        'captcha/generate' => 'captcha_generate.php',
        'captcha/unblock' => 'captcha_unblock.php',
        'setup' => 'setup.php',
        'setup/verify-captcha' => 'setup.php',
        'unlock' => 'unlock.php',
        'import' => 'import.php',
        'import_private_key' => 'import_private_key.php',
        'logout' => 'logout.php',
        'get_wallets' => 'get_wallets.php',
        'get_addresses' => 'get_addresses.php',
        'get_balance' => 'get_balance.php',
        'get_transactions' => 'get_transactions.php',
        'get_transactions_view' => 'get_transactions_view.php',
        'dashboard' => 'dashboard.php',
        'receive' => 'receive.php',
        'change_password' => 'change_password.php',
        'import_backup' => 'import_backup.php',
        'addresses_with_balance' => 'addresses_with_balance.php',
        'prices' => 'prices.php',
        'generate_qr' => 'generate_qr.php',
        'proof-of-reserves' => 'proof_of_reserves.php',
        'security-audit-info' => 'security_audit_info.php',
        'public-key' => 'public_key.php',
        'verify-public-key' => 'verify_public_key.php',
        'get_utxos' => 'get_utxos.php',
        'sync_balances' => 'sync_balances.php',
        'sync_transactions' => 'sync_transactions.php',
        'address_check' => 'address_check.php',
        'address_check/derive' => 'address_check.php',
        'fee_estimation' => 'fee_estimation.php',
        'explorer' => 'explorer.php',
        'explorer/coins' => 'explorer.php',
        'explorer/address' => 'explorer.php',
        'explorer/tx' => 'explorer.php',
        'explorer/bitcoin/blocks' => 'explorer.php',
        'explorer/bitcoin/fees' => 'explorer.php',
        'explorer/bitcoin/stats' => 'explorer.php',
        'admin/login' => 'admin_login.php',
        'admin/login/generate' => 'admin_login_generate.php',
        'admin/stats' => 'admin_stats.php',
        'admin/change_password' => 'admin_change_password.php',
        'admin/wallets' => 'admin_wallets.php',
        'admin/sessions' => 'admin_sessions.php',
        'admin/login_activity' => 'admin_login_activity.php',
        'admin/transactions' => 'admin_transactions.php',
        'admin/delete_wallet' => 'admin_delete_wallet.php',
        'admin/delete_session' => 'admin_delete_session.php',
        'admin/wallet_details' => 'admin_wallet_details.php',
        'admin/incident' => 'admin_incident.php',
        'admin/incident/resolve' => 'admin_incident.php',
        'admin/incident/delete' => 'admin_incident.php',
        'admin/changenow' => 'admin_changenow.php',
        'admin/changenow/list' => 'admin_changenow.php',
        'admin/changenow/update' => 'admin_changenow.php',
        'admin/changenow/delete' => 'admin_changenow.php',
'support_tickets' => 'support_tickets.php',
        'support_tickets/list' => 'support_tickets.php',
        'support_tickets/resolve' => 'support_tickets.php',
        'support_tickets/delete' => 'support_tickets.php',
        'admin/env' => 'admin_env.php',
        'changenow' => 'changenow.php',
        'changenow/currencies' => 'changenow.php',
        'changenow/estimate' => 'changenow.php',
        'changenow/create' => 'changenow.php',
        'changenow/range' => 'changenow.php',
        'changenow/list' => 'changenow.php',
    ];

    // Pattern routes with URL parameters, e.g. 'changenow/status/{id}'.
    private static $patternRoutes = [
        'changenow/status/{id}' => 'changenow_status.php',
        'explorer/bitcoin/block/{query}' => 'explorer_bitcoin_block.php',
    ];

    public static function dispatch($path, $method) {
        $seg = substr($path, 4); // strip /api
        $seg = ltrim($seg, '/');
        $seg = rtrim($seg, '/');
        $seg = $seg === '' ? '/' : $seg;

        $key = strtolower($seg);

        // Global security layer (mirrors Node middleware: ban, rate limit, CSRF origin check)
        self::applySecurity($key, $method);

        $file = null;
        $params = [];

        if (isset(self::$routes[$key])) {
            $file = self::$routes[$key];
        } else {
            // Try pattern routes (e.g. /changenow/status/ABC123)
            $parts = explode('/', $key);
            foreach (self::$patternRoutes as $pattern => $target) {
                $patternParts = explode('/', $pattern);
                if (count($parts) !== count($patternParts)) continue;
                $matched = true;
                $captured = [];
                foreach ($patternParts as $i => $pp) {
                    if (strpos($pp, '{') === 0 && substr($pp, -1) === '}') {
                        $captured[trim($pp, '{}')] = urldecode($parts[$i]);
                    } elseif ($pp !== $parts[$i]) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    $file = $target;
                    $params = $captured;
                    break;
                }
            }
        }

        if ($file === null) {
            return jsonResponse(['error' => 'Not found'], 404);
        }

        $target = __DIR__ . '/endpoints/' . $file;
        if (!is_file($target)) {
            return jsonResponse(['error' => 'Not found'], 404);
        }

        $body = readJsonBody();
        $GLOBALS['api_path'] = $path;
        $GLOBALS['api_method'] = $method;
        $GLOBALS['api_body'] = $body;
        $GLOBALS['api_params'] = $params;

        include $target;
    }

    private static function applySecurity($key, $method) {
        $ip = clientIp();

        // 1. Banned IPs are rejected on every request
        if (BanList::isBanned($ip)) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }

// 2. Global rate limit: DISABLED
        /*
        if (strpos($key, 'captcha') !== 0 && $key !== 'status' && $key !== 'incidents') {
            try {
                RateLimitStore::init(['windowMs' => 60000]);
                $r = RateLimitStore::increment('api:' . $ip);
                if ($r['totalHits'] > 600) {
                    BanList::ban($ip);
                    jsonResponse(['error' => 'Too many requests, please try again later.'], 429);
                }
            } catch (Throwable $e) {
            }
        }
        */

        // 3. CSRF protection: non-GET requests must come from the app itself (mirror Node csrfMiddleware)
        if (!in_array($method, ['GET', 'HEAD'], true) && $key !== 'status' && $key !== 'incidents') {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (!self::isAllowedOrigin($origin, $host) && !self::isAllowedOrigin($referer, $host)) {
                jsonResponse(['error' => 'CSRF validation failed'], 403);
            }
        }
    }

    private static function isAllowedOrigin($value, $host) {
        if (!$value) return false;
        $parts = parse_url($value);
        $originHost = $parts['host'] ?? '';
        if ($originHost === '') return false;
        if ($originHost === $host) return true;
        $allowed = ['localhost', '127.0.0.1', 'prunebit.com', 'www.prunebit.com', 'prunebit.space', 'www.prunebit.space'];
        $extra = env('ALLOWED_ORIGINS', '');
        if ($extra !== '') {
            $allowed = array_merge($allowed, array_map('trim', explode(',', $extra)));
        }
        return in_array($originHost, $allowed, true);
    }

    public static function body($key = null, $default = null) {
        $body = $GLOBALS['api_body'] ?? [];
        if ($key === null) return $body;
        return $body[$key] ?? $default;
    }

    public static function param($key, $default = null) {
        return $GLOBALS['api_params'][$key] ?? $default;
    }

    public static function requirePost() {
        if (($GLOBALS['api_method'] ?? 'GET') !== 'POST') {
            jsonResponse(['error' => 'Method not allowed'], 405);
            exit;
        }
    }
}





