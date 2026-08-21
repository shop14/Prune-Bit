<?php

Api::requirePost();
requireAdminToken();

$pbSegs = explode('/', strtolower(trim($GLOBALS['api_path'] ?? '', '/')));
$pbAction = (string) end($pbSegs);

if ($pbAction === 'resolve') {
    try {
        $incidentId = Api::body('incident_id');
        if (!$incidentId) {
            jsonResponse(['error' => 'incident_id is required'], 400);
        }
        Database::execute('UPDATE incidents SET resolved = 1 WHERE id = ?', [$incidentId]);
        jsonResponse(['success' => true, 'message' => 'Incident resolved']);
    } catch (Throwable $e) {
        error_log('Resolve incident error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to resolve incident'], 500);
    }
} elseif ($pbAction === 'delete') {
    try {
        $incidentId = Api::body('incident_id');
        if (!$incidentId) {
            jsonResponse(['error' => 'incident_id is required'], 400);
        }
        Database::execute('DELETE FROM incidents WHERE id = ?', [$incidentId]);
        jsonResponse(['success' => true, 'message' => 'Incident deleted']);
    } catch (Throwable $e) {
        error_log('Delete incident error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete incident'], 500);
    }
} elseif ($pbAction === 'create') {
    try {
        $title = Api::body('title');
        if (!$title) {
            jsonResponse(['error' => 'Title required'], 400);
        }
        Database::execute(
            'INSERT INTO incidents (title, description, created_at, resolved) VALUES (?, ?, NOW(), 0)',
            [$title, Api::body('description') ?: '']
        );
        jsonResponse(['success' => true, 'message' => 'Incident added successfully']);
    } catch (Throwable $e) {
        error_log('Add incident error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to add incident'], 500);
    }
} else {
    jsonResponse(['error' => 'Unknown action'], 400);
}
