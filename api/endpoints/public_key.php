<?php

try {
    $publicKey = '04a34b99f22c790c4e36b2b3c2c35a36db06226e41c692fc82b8b56ac1c540c5bd5b8dec5235a0fa872a664b49b3c4b8e8f0c3d5e8f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3';
    header('Content-Type: text/plain');
    echo $publicKey;
    exit;
} catch (Throwable $e) {
    error_log('Public key error: ' . ($e->getCode() ? $e->getCode() : 'public_key_failed'));
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo 'Error fetching public key';
    exit;
}
