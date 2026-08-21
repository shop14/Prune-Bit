<?php

try {
    // Honest disclosure: no third-party security audit has been completed yet.
    // Claiming audits or certifications that do not exist is prohibited.
    jsonResponse([
        'success' => true,
        'lastAuditDate' => null,
        'auditor' => null,
        'auditType' => null,
        'message' => 'No independent third-party security audit has been completed yet. The codebase is open for public review.',
        'scope' => [],
        'findings' => null,
        'remediationStatus' => null,
        'reportUrl' => null,
        'nextAuditScheduled' => null,
        'certifications' => [],
    ]);
} catch (Throwable $e) {
    error_log('Security audit info error: ' . ($e->getCode() ? $e->getCode() : 'security_audit_failed'));
    jsonResponse(['error' => 'Failed to fetch security audit info'], 500);
}
