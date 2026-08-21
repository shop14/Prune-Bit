<?php

class Bip39 {
    private static $wordlist = null;

    private static function wordlist() {
        if (self::$wordlist === null) {
            $file = __DIR__ . '/../config/english.txt';
            if (!file_exists($file)) {
                throw new Exception('BIP39 wordlist not found: ' . $file);
            }
            $words = array_map('trim', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            if (isset($words[0])) {
                $words[0] = preg_replace('/^\xEF\xBB\xBF/', '', $words[0]);
            }
            self::$wordlist = $words;
        }
        return self::$wordlist;
    }

    public static function mnemonicToSeed($mnemonic, $passphrase = '') {
        $mnemonic = trim($mnemonic);
        $mnemonic = preg_replace('/\s+/', ' ', $mnemonic);
        $salt = 'mnemonic' . $passphrase;
        // PBKDF2-HMAC-SHA512, 2048 iterations, 64 bytes
        $seed = hash_pbkdf2('sha512', $mnemonic, $salt, 2048, 64, true);
        return $seed;
    }

    public static function entropyToMnemonic($entropyHex) {
        $entropy = hex2bin($entropyHex);
        $entropyBits = strlen($entropy) * 8;
        if (!in_array($entropyBits, [128, 160, 192, 224, 256])) {
            throw new Exception('Invalid entropy length');
        }
        $checksumBits = $entropyBits / 32;
        $hash = hash('sha256', $entropy, true);
        $bits = self::bytesToBits($entropy) . substr(self::bytesToBits($hash), 0, $checksumBits);
        $words = [];
        for ($i = 0; $i < strlen($bits) / 11; $i++) {
            $idx = bindec(substr($bits, $i * 11, 11));
            $words[] = self::wordlist()[$idx];
        }
        return implode(' ', $words);
    }

    private static function bytesToBits($bytes) {
        $bits = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        return $bits;
    }

    private static function wordsToBits($words) {
        $map = array_flip(self::wordlist());
        $bits = '';
        foreach ($words as $word) {
            $word = strtolower(trim($word));
            if (!isset($map[$word])) {
                throw new Exception('Invalid mnemonic word: ' . $word);
            }
            $bits .= str_pad(decbin($map[$word]), 11, '0', STR_PAD_LEFT);
        }
        return $bits;
    }

    public static function generateMnemonic($strength = 128) {
        if (!in_array($strength, [128, 160, 192, 224, 256])) {
            throw new Exception('Invalid mnemonic strength');
        }
        $bytes = random_bytes($strength / 8);
        return self::entropyToMnemonic(bin2hex($bytes));
    }

    public static function validateMnemonic($mnemonic) {
        try {
            $words = preg_split('/\s+/', trim($mnemonic));
            $count = count($words);
            if (!in_array($count, [12, 15, 18, 21, 24])) {
                return false;
            }
            $bits = self::wordsToBits($words);
            $entropyBits = (int)(strlen($bits) * 32 / 33);
            $checksumBits = $entropyBits / 32;
            $entropyHex = self::bitsToHex(substr($bits, 0, $entropyBits));
            $expectedChecksum = substr(self::bytesToBits(hash('sha256', hex2bin($entropyHex), true)), 0, $checksumBits);
            $actualChecksum = substr($bits, $entropyBits, $checksumBits);
            return $expectedChecksum === $actualChecksum;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function mnemonicToEntropy($mnemonic) {
        $words = preg_split('/\s+/', trim($mnemonic));
        $bits = self::wordsToBits($words);
        $entropyBits = (int)(strlen($bits) * 32 / 33);
        return self::bitsToHex(substr($bits, 0, $entropyBits));
    }

    private static function bitsToHex($bits) {
        $hex = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $hex .= sprintf('%02x', bindec(substr($bits, $i, 8)));
        }
        return $hex;
    }
}
