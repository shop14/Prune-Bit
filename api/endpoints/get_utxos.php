<?php

Api::requirePost();

try {
    $address = Api::body('address');
    $coin = Api::body('coin');

    if (!$address || !$coin) {
        jsonResponse(['error' => 'Address and coin are required'], 400);
    }

    $utxos = BlockchainAPI::getUTXOs($address, $coin);

    jsonResponse(['success' => true, 'utxos' => $utxos]);
} catch (Throwable $e) {
    error_log('UTXO error: ' . ($e->getCode() ? $e->getCode() : 'utxo_failed'));
    jsonResponse(['error' => 'Blockchain API error'], 500);
}
