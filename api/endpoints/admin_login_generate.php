<?php

Api::requirePost();

try {
    $c = Captcha::generate();
    jsonResponse(['success' => true, 'captcha_token' => $c['token'], 'captcha_image' => $c['image']]);
} catch (Throwable $e) {
    error_log('Captcha generate error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to generate captcha'], 500);
}
