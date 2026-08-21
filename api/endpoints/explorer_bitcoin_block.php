<?php

require_once __DIR__ . '/_explorer_helpers.php';

$query = isset($_GET['query']) ? (string) $_GET['query'] : Api::param('query');
$query = $query !== '' ? $query : (isset($_GET['hash']) ? (string) $_GET['hash'] : '');

if ($query === '') {
    jsonResponse(['error' => 'Block hash or height is required'], 400);
}

try {
    $hash = $query;
    if (ctype_digit($query)) {
        $hr = httpRequest('https://mempool.space/api/block-height/' . (int) $query, 'GET');
        if ($hr === null || !is_string($hr['body']) || strlen(trim($hr['body'])) < 10) {
            jsonResponse(['success' => false, 'error' => 'Block not found'], 404);
        }
        $hash = trim($hr['body']);
    }
    $block = expl_fetchBlockByHash($hash);
    if ($block === null) {
        jsonResponse(['success' => false, 'error' => 'Block not found'], 404);
    } else {
        jsonResponse(['success' => true, 'block' => $block]);
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
