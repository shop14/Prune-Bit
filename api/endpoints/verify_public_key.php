<?php

try {
    jsonResponse([
        'success' => true,
        'message' => 'Public key verification endpoint',
        'verificationMethods' => [
            [
                'name' => 'Bitcoin Explorer',
                'url' => 'https://blockstream.info/address/bc1qprunebitcoldwallet1addressexample123456789',
                'description' => 'Verify BTC cold wallet holdings on-chain',
            ],
            [
                'name' => 'Ethereum Explorer',
                'url' => 'https://etherscan.io/address/0x742d35Cc6634C0532925a3b844Bc9e7595f8fEb1',
                'description' => 'Verify ETH cold wallet holdings on-chain',
            ],
            [
                'name' => 'Signature Verification',
                'url' => '/api/verify-signature',
                'description' => 'Verify messages signed by our backend key',
            ],
        ],
        'instructions' => 'Use the explorers above to verify that the cold wallet addresses hold the claimed balances. The public key can be used to verify signatures from our backend services.',
    ]);
} catch (Throwable $e) {
    error_log('Verify public key error: ' . ($e->getCode() ? $e->getCode() : 'verify_public_key_failed'));
    jsonResponse(['error' => 'Failed to fetch verification info'], 500);
}
