<?php

try {
    // Honest disclosure: PruneBit is a non-custodial wallet. User funds are
    // never held, pooled, or controlled by the service, so a custodial
    // proof-of-reserves does not apply. Keys are encrypted on the server only
    // to support optional session features and can be wiped by the user.
    jsonResponse([
        'success' => true,
        'custodial' => false,
        'reserveRatio' => null,
        'lastVerification' => null,
        'message' => 'PruneBit is a non-custodial wallet: it never holds user funds, so no reserve backing is required or claimed.',
        'coldWallets' => [],
        'attestation' => [
            'auditor' => null,
            'date' => gmdate('Y-m-d'),
            'method' => 'Not applicable (non-custodial)',
            'reportUrl' => null,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Proof of reserves error: ' . ($e->getCode() ? $e->getCode() : 'proof_of_reserves_failed'));
    jsonResponse(['error' => 'Failed to fetch proof of reserves data'], 500);
}
