<?php

try {
    $incidents = Database::getIncidents();
    return jsonResponse(['success' => true, 'incidents' => $incidents ?? []]);
} catch (Throwable $e) {
    error_log('Get incidents error: ' . $e->getMessage());
    return jsonResponse(['success' => true, 'incidents' => []]);
}
