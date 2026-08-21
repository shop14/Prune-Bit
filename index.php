<?php

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/api/Api.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$dir = str_replace('\\', '/', __DIR__);
$base = '';
if ($docRoot !== '' && strpos($dir, $docRoot) === 0) {
    $base = substr($dir, strlen($docRoot));
}
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $uri;
if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
if ($path === '') $path = '/';

// Merged layout: /html/xxx.html is the same page set served at /xxx.html.
if ($path === '/html') {
    $path = '/';
} elseif (strpos($path, '/html/') === 0) {
    $path = substr($path, 5);
}

// Dispatch API requests
if (strpos($path, '/api') === 0) {
    Api::dispatch($path, $method);
}

// Serve static frontend assets
if (!in_array($method, ['GET', 'HEAD'])) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$frontendDir = config('FRONTEND_DIR', null);
if ($frontendDir === null || !is_dir($frontendDir . '/html')) {
    // Dev layout: frontend is a sibling folder (frontend/html).
    $frontendDir = __DIR__ . '/../frontend';
    // Host layout A: frontend copied inside the backend root (frontend/html).
    if (!is_dir($frontendDir . '/html')) $frontendDir = __DIR__ . '/frontend';
    // Host layout B: frontend contents merged into the document root (html/css/js/img at root).
    if (!is_dir($frontendDir . '/html')) $frontendDir = __DIR__;
}
$map = [
    '/css/' => 'css',
    '/js/' => 'js',
    '/img/' => 'img',
];
$assetDir = null;
foreach ($map as $prefix => $dir) {
    if (strpos($path, $prefix) === 0) {
        $assetDir = $frontendDir . '/' . $dir;
        $rel = substr($path, strlen($prefix));
        break;
    }
}
if ($assetDir === null) {
    $assetDir = $frontendDir . '/html';
    $rel = ltrim($path, '/');
    if ($rel === '') $rel = 'index.html';
}

$realAssetDir = realpath($assetDir);
$full = realpath($assetDir . '/' . $rel);
if ($realAssetDir === false || $full === false || strpos($full, $realAssetDir) !== 0 || !is_file($full)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    if (is_file($frontendDir . '/html/404.html')) {
        echo file_get_contents($frontendDir . '/html/404.html');
    } else {
        echo '<h1>404 Not Found</h1>';
    }
    exit;
}

// Cache policy mirrors Node: html no-cache, js no-cache, css/img 7d
if (preg_match('/\.html?$/i', $rel)) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
} elseif (preg_match('/\.css$/i', $rel) || preg_match('/\.(png|jpe?g|svg|webp|gif|ico|apk)$/i', $rel)) {
    header('Cache-Control: public, max-age=604800');
} elseif (preg_match('/\.js$/i', $rel)) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

// Extension-based MIME map: mime_content_type() is unreliable on Windows
// (returns text/plain for css/js, text/html for some .js files).
$ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
$mimeTypes = [
    'html' => 'text/html; charset=utf-8',
    'htm' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'mjs' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'xml' => 'application/xml; charset=utf-8',
    'txt' => 'text/plain; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'ico' => 'image/x-icon',
    'apk' => 'application/vnd.android.package-archive',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'otf' => 'font/otf',
    'eot' => 'application/vnd.ms-fontobject',
];
$mime = $mimeTypes[$ext] ?? (mime_content_type($full) ?: 'application/octet-stream');
header('Content-Type: ' . $mime);
if ($method === 'HEAD') exit;
readfile($full);
