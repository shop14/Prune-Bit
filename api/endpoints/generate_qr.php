<?php
try {
    Api::requirePost();

    $address = Api::body('address');
    if (!$address) {
        jsonResponse(['error' => 'Address is required'], 400);
    }
    $size = Api::body('size', 192);
    $size = (int) (is_numeric($size) ? $size : 192);
    if ($size <= 0) $size = 192;

    $qr = QRCode::toDataUrl($address, $size);
    if (!$qr) {
        jsonResponse(['error' => 'Internal server error'], 500);
    }

    jsonResponse(['success' => true, 'qr' => $qr]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Internal server error'], 500);
}
