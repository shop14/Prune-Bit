<?php

class QRCode {
    private static $cache = [];

    public static function toDataUrl($text, $width = 192) {
        if ($text === null) $text = '';
        $text = (string) $text;
        $cacheKey = $text . '|' . $width;
        if (isset(self::$cache[$cacheKey])) return self::$cache[$cacheKey];

        $size = max(64, (int) $width);
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($text);
        $resp = httpRequest($url, 'GET', null, ['User-Agent: PruneBit/1.0']);
        if ($resp !== null && $resp['status'] === 200 && $resp['body'] !== '') {
            $dataUrl = 'data:image/png;base64,' . base64_encode($resp['body']);
            self::$cache[$cacheKey] = $dataUrl;
            return $dataUrl;
        }
        self::$cache[$cacheKey] = false;
        return false;
    }

    public static function encode($text, $width = 192) {
        return self::toDataUrl($text, $width);
    }

    public static function decode($dataUrl) {
        return null;
    }
}
