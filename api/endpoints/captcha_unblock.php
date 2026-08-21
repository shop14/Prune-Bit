<?php

try {
    $token = Api::body('captcha_token');
    $code = Api::body('captcha_code');
    $result = Captcha::unblock($token, $code, clientIp());
    if (!$result['success']) {
        return jsonResponse(['success' => false, 'error' => $result['error']], 400);
    }
    return jsonResponse(['success' => true, 'message' => 'Access restored.']);
} catch (Throwable $e) {
    error_log('Captcha unblock error: ' . $e->getMessage());
    return jsonResponse(['success' => false, 'error' => 'Failed to unblock. Please try again.'], 500);
}
