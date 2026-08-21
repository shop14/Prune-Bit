<?php

class Bip32 {
    private $privateKey; // 32-byte hex (lowercase) or null
    private $publicKey;  // 33-byte compressed hex (lowercase)
    private $chainCode;  // 32-byte hex
    private $depth;      // int
    private $index;      // int (>= 0x80000000 for hardened)
    private $parentFingerprint; // 4-byte hex

    public function __construct($privateKey, $publicKey, $chainCode, $depth = 0, $index = 0, $parentFingerprint = '00000000') {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->chainCode = $chainCode;
        $this->depth = $depth;
        $this->index = $index;
        $this->parentFingerprint = $parentFingerprint;
    }

    public function getPrivateKey() {
        return $this->privateKey; // hex string (32 bytes)
    }

    public function getPublicKey() {
        return $this->publicKey; // hex string (33 bytes compressed)
    }

    public function getChainCode() {
        return $this->chainCode;
    }

    public function isNeutered() {
        return $this->privateKey === null;
    }

    public function derive($index) {
        $isHardened = $index >= 0x80000000;
        $data = '';
        if ($isHardened) {
            if ($this->privateKey === null) {
                throw new Exception('Cannot derive a hardened child from a public-only key');
            }
            $data = "\x00" . hex2bin($this->privateKey) . pack('N', $index);
        } else {
            $data = hex2bin($this->publicKey) . pack('N', $index);
        }
        $I = hash_hmac('sha512', $data, hex2bin($this->chainCode), true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32, 32);

        $N = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        $ILnum = gmp_init(bin2hex($IL), 16);
        if (gmp_cmp($ILnum, $N) >= 0 || gmp_cmp($ILnum, 0) === 0) {
            return $this->derive($index + 1);
        }

        $childPrivateKey = null;
        $childPublicKey = null;
        if ($this->privateKey !== null) {
            $ki = gmp_add(gmp_init($this->privateKey, 16), $ILnum);
            $ki = gmp_mod($ki, $N);
            if (gmp_cmp($ki, 0) === 0) {
                return $this->derive($index + 1);
            }
            $childPrivateKey = str_pad(gmp_strval($ki, 16), 64, '0', STR_PAD_LEFT);
            $childPublicKey = Secp256k1::privateKeyToPublicKey($childPrivateKey);
        } else {
            // from public key: point(kpar) + IL*G
            $point = Secp256k1::pointAdd(
                [gmp_init(substr($this->publicKey, 2, 64), 16), self::yFromX(gmp_init(substr($this->publicKey, 2, 64), 16))],
                Secp256k1::scalarBaseMul($ILnum)
            );
            if ($point === null) {
                return $this->derive($index + 1);
            }
            list($x, $y) = $point;
            $xHex = str_pad(gmp_strval($x, 16), 64, '0', STR_PAD_LEFT);
            $childPublicKey = (gmp_intval(gmp_mod($y, 2)) === 0 ? '02' : '03') . $xHex;
        }

        $fingerprint = substr(hash('ripemd160', hash('sha256', hex2bin($this->publicKey), true), true), 0, 4);

        return new Bip32($childPrivateKey, $childPublicKey, bin2hex($IR), $this->depth + 1, $index, bin2hex($fingerprint));
    }

    private static function yFromX($x) {
        $p = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
        // y^2 = x^3 + 7
        $y2 = gmp_mod(gmp_add(gmp_mul(gmp_mul($x, $x), $x), 7), $p);
        // y = y2^((p+1)/4) mod p
        $exp = gmp_add($p, 1);
        $exp = gmp_div_q($exp, 4);
        return gmp_powm($y2, $exp, $p);
    }

    public static function fromSeed($seed, $version = null) {
        $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
        $IL = bin2hex(substr($I, 0, 32));
        $IR = bin2hex(substr($I, 32, 32));
        $node = new Bip32($IL, Secp256k1::privateKeyToPublicKey($IL), $IR, 0, 0, '00000000');
        return $node;
    }

    public static function derivePath($path, $seed) {
        if (strpos($path, 'm/') === 0) {
            $path = substr($path, 2);
        }
        $node = self::fromSeed($seed);
        if ($path === '') {
            return $node;
        }
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            $hardened = false;
            if (substr($part, -1) === "'" || substr($part, -1) === 'h' || substr($part, -1) === 'H') {
                $hardened = true;
                $part = substr($part, 0, -1);
            }
            $index = (int) $part;
            if ($hardened) {
                $index += 0x80000000;
            }
            $node = $node->derive($index);
        }
        return $node;
    }

    public function toBase58() {
        $version = $this->privateKey !== null ? '0488ade4' : '0488b21e';
        $payload = hex2bin($version)
            . chr($this->depth)
            . hex2bin($this->parentFingerprint)
            . pack('N', $this->index)
            . hex2bin($this->chainCode);
        if ($this->privateKey !== null) {
            $payload .= "\x00" . hex2bin($this->privateKey);
        } else {
            $payload .= hex2bin($this->publicKey);
        }
        return Base58::base58CheckEncode($payload);
    }

    public static function fromBase58($string) {
        $data = Base58::base58CheckDecode($string);
        $version = bin2hex(substr($data, 0, 4));
        $depth = ord($data[4]);
        $parentFingerprint = bin2hex(substr($data, 5, 4));
        $index = unpack('N', substr($data, 9, 4))[1];
        $chainCode = bin2hex(substr($data, 13, 32));
        if ($version === '0488ade4') {
            $privateKey = bin2hex(substr($data, 46, 32));
            $publicKey = Secp256k1::privateKeyToPublicKey($privateKey);
            return new Bip32($privateKey, $publicKey, $chainCode, $depth, $index, $parentFingerprint);
        } else {
            $publicKey = bin2hex(substr($data, 45, 33));
            return new Bip32(null, $publicKey, $chainCode, $depth, $index, $parentFingerprint);
        }
    }

    public static function fromPrivateKey($privateKeyHex, $chainCodeHex) {
        return new Bip32(strtolower($privateKeyHex), Secp256k1::privateKeyToPublicKey(strtolower($privateKeyHex)), strtolower($chainCodeHex));
    }
}
