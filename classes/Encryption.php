<?php

class Encryption {
    const PBKDF2_ITERATIONS = 100000;

    public static function deriveKey($password, $salt, $iterations = self::PBKDF2_ITERATIONS) {
        return hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
    }

    public static function encrypt($text, $password) {
        $salt = bin2hex(random_bytes(16));
        $iv = bin2hex(random_bytes(12));
        $key = self::deriveKey($password, $salt);
        $ciphertext = openssl_encrypt($text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, hex2bin($iv), $tag, '', 16);
        if ($ciphertext === false) {
            throw new Exception('Encryption failed');
        }
        return [
            'ciphertext' => bin2hex($ciphertext),
            'salt' => $salt,
            'iv' => $iv,
            'tag' => bin2hex($tag)
        ];
    }

    public static function decrypt($encryptedData, $password) {
        $key = self::deriveKey($password, $encryptedData['salt']);
        try {
            $plaintext = openssl_decrypt(
                hex2bin($encryptedData['ciphertext']),
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                hex2bin($encryptedData['iv']),
                hex2bin($encryptedData['tag'])
            );
            if ($plaintext === false) {
                throw new Exception('GCM auth failed');
            }
            return $plaintext;
        } catch (\Throwable $e) {
            $legacyKey = self::deriveLegacyKey($password, $encryptedData['salt']);
            $plaintext = openssl_decrypt(
                hex2bin($encryptedData['ciphertext']),
                'aes-256-gcm',
                $legacyKey,
                OPENSSL_RAW_DATA,
                hex2bin($encryptedData['iv']),
                hex2bin($encryptedData['tag'])
            );
            if ($plaintext === false) {
                throw new Exception('Decryption failed');
            }
            return $plaintext;
        }
    }

    public static function deriveLegacyKey($password, $salt) {
        return self::scrypt($password, $salt, 16384, 8, 1, 32);
    }

    public static function hashMnemonic($mnemonic) {
        return hash('sha256', $mnemonic);
    }

    /**
     * Pure PHP scrypt (RFC 7914). Node crypto.scrypt defaults: N=16384, r=8, p=1, maxmem 32MB.
     */
    public static function scrypt($password, $salt, $N, $r, $p, $dkLen) {
        $blockSize = 128 * $r;
        $B = hash_pbkdf2('sha256', $password, $salt, 1, $p * $blockSize, true);
        for ($i = 0; $i < $p; $i++) {
            $bi = substr($B, $i * $blockSize, $blockSize);
            $mixed = self::romix($bi, $N, $r);
            $B = substr_replace($B, $mixed, $i * $blockSize, $blockSize);
        }
        return hash_pbkdf2('sha256', $password, $B, 1, $dkLen, true);
    }

    private static function romix($B, $N, $r) {
        $blockSize = 128 * $r;
        $V = [];
        $X = $B;
        for ($i = 0; $i < $N; $i++) {
            $V[$i] = $X;
            $X = self::blockMix($X, $r);
        }
        for ($i = 0; $i < $N; $i++) {
            $j = self::integerify($X, $r) % $N;
            $X = self::blockMix($X ^ $V[$j], $r);
        }
        return $X;
    }

    private static function blockMix($B, $r) {
        $blocks = str_split($B, 64);
        $count = count($blocks);
        $X = $blocks[$count - 1];
        $Y = [];
        for ($i = 0; $i < $count; $i++) {
            $X = self::salsa20_8($X ^ $blocks[$i]);
            $Y[$i] = $X;
        }
        $out = '';
        for ($i = 0; $i < $r; $i++) {
            $out .= $Y[$i * 2];
        }
        for ($i = 0; $i < $r; $i++) {
            $out .= $Y[$i * 2 + 1];
        }
        return $out;
    }

    private static function integerify($X, $r) {
        $last = substr($X, -64);
        $words = unpack('V16', $last);
        return $words[1]; // first word of the last 64 bytes, little-endian
    }

    private static function salsa20_8($input) {
        $in = array_values(unpack('V16', $input));
        $x = $in;
        for ($i = 0; $i < 4; $i++) {
            $x[4]  = ($x[4]  ^ self::rotl(($x[0]  + $x[12]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[8]  = ($x[8]  ^ self::rotl(($x[4]  + $x[0]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[12] = ($x[12] ^ self::rotl(($x[8]  + $x[4]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[0]  = ($x[0]  ^ self::rotl(($x[12] + $x[8]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[9]  = ($x[9]  ^ self::rotl(($x[5]  + $x[1]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[13] = ($x[13] ^ self::rotl(($x[9]  + $x[5]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[1]  = ($x[1]  ^ self::rotl(($x[13] + $x[9]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[5]  = ($x[5]  ^ self::rotl(($x[1]  + $x[13]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[14] = ($x[14] ^ self::rotl(($x[10] + $x[6]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[2]  = ($x[2]  ^ self::rotl(($x[14] + $x[10]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[6]  = ($x[6]  ^ self::rotl(($x[2]  + $x[14]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[10] = ($x[10] ^ self::rotl(($x[6]  + $x[2]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[3]  = ($x[3]  ^ self::rotl(($x[15] + $x[11]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[7]  = ($x[7]  ^ self::rotl(($x[3]  + $x[15]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[11] = ($x[11] ^ self::rotl(($x[7]  + $x[3]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[15] = ($x[15] ^ self::rotl(($x[11] + $x[7]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[1]  = ($x[1]  ^ self::rotl(($x[0]  + $x[3]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[2]  = ($x[2]  ^ self::rotl(($x[1]  + $x[0]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[3]  = ($x[3]  ^ self::rotl(($x[2]  + $x[1]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[0]  = ($x[0]  ^ self::rotl(($x[3]  + $x[2]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[6]  = ($x[6]  ^ self::rotl(($x[5]  + $x[4]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[7]  = ($x[7]  ^ self::rotl(($x[6]  + $x[5]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[4]  = ($x[4]  ^ self::rotl(($x[7]  + $x[6]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[5]  = ($x[5]  ^ self::rotl(($x[4]  + $x[7]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[11] = ($x[11] ^ self::rotl(($x[10] + $x[9]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[8]  = ($x[8]  ^ self::rotl(($x[11] + $x[10]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[9]  = ($x[9]  ^ self::rotl(($x[8]  + $x[11]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[10] = ($x[10] ^ self::rotl(($x[9]  + $x[8]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
            $x[12] = ($x[12] ^ self::rotl(($x[15] + $x[14]) & 0xFFFFFFFF, 7)) & 0xFFFFFFFF;
            $x[13] = ($x[13] ^ self::rotl(($x[12] + $x[15]) & 0xFFFFFFFF, 9)) & 0xFFFFFFFF;
            $x[14] = ($x[14] ^ self::rotl(($x[13] + $x[12]) & 0xFFFFFFFF, 13)) & 0xFFFFFFFF;
            $x[15] = ($x[15] ^ self::rotl(($x[14] + $x[13]) & 0xFFFFFFFF, 18)) & 0xFFFFFFFF;
        }
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= pack('V', ($x[$i] + $in[$i]) & 0xFFFFFFFF);
        }
        return $out;
    }

    private static function rotl($x, $b) {
        return (($x << $b) | ($x >> (32 - $b))) & 0xFFFFFFFF;
    }
}
