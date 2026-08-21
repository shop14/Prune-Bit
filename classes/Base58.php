<?php

class Base58 {
    const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function encode($data, $alphabet = null) {
        if ($alphabet === null) {
            $alphabet = self::ALPHABET;
        }
        $hex = bin2hex($data);
        $n = gmp_init(0, 16);
        if ($hex !== '') {
            $n = gmp_init($hex, 16);
        }
        $result = '';
        while (gmp_cmp($n, 0) > 0) {
            $n = gmp_div_qr($n, 58);
            $result .= $alphabet[(int) gmp_intval($n[1])];
            $n = $n[0];
        }
        for ($i = 0; $i < strlen($data) && ord($data[$i]) === 0; $i++) {
            $result .= $alphabet[0];
        }
        return strrev($result);
    }

    public static function decode($str, $alphabet = null) {
        if ($alphabet === null) {
            $alphabet = self::ALPHABET;
        }
        $n = gmp_init(0, 10);
        for ($i = 0; $i < strlen($str); $i++) {
            $c = $str[$i];
            $idx = strpos($alphabet, $c);
            if ($idx === false) {
                throw new Exception('Invalid Base58 character: ' . $c);
            }
            $n = gmp_add(gmp_mul($n, 58), $idx);
        }
        $hex = gmp_strval($n, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $decoded = hex2bin($hex);
        for ($i = 0; $i < strlen($str) && $str[$i] === $alphabet[0]; $i++) {
            $decoded = "\x00" . $decoded;
        }
        return $decoded;
    }

    public static function base58CheckEncode($payload, $alphabet = null) {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        return self::encode($payload . $checksum, $alphabet);
    }

    public static function base58CheckEncodeWithAlphabet($payload, $alphabet) {
        return self::base58CheckEncode($payload, $alphabet);
    }

    public static function base58CheckDecode($str) {
        $decoded = self::decode($str);
        if (strlen($decoded) < 4) {
            throw new Exception('Invalid Base58Check string');
        }
        $payload = substr($decoded, 0, -4);
        $checksum = substr($decoded, -4);
        $computed = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        if (!hash_equals($checksum, $computed)) {
            throw new Exception('Invalid Base58Check checksum');
        }
        return $payload;
    }

    public static function hash160($data) {
        return hash('ripemd160', hash('sha256', $data, true), true);
    }
}
