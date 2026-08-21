<?php

class Secp256k1 {
    public static $P = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F';
    public static $N = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';
    public static $Gx = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
    public static $Gy = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';

    private static function p() { return gmp_init(self::$P, 16); }
    private static function n() { return gmp_init(self::$N, 16); }
    private static function gx() { return gmp_init(self::$Gx, 16); }
    private static function gy() { return gmp_init(self::$Gy, 16); }

    public static function mod($a, $m) {
        $r = gmp_mod($a, $m);
        return $r;
    }

    public static function modInv($a, $m) {
        return gmp_invert($a, $m);
    }

    // Point add: returns [x, y] or null for infinity
    public static function pointAdd($p1, $p2) {
        if ($p1 === null) return $p2;
        if ($p2 === null) return $p1;
        $p = self::p();
        list($x1, $y1) = $p1;
        list($x2, $y2) = $p2;
        if (gmp_cmp($x1, $x2) === 0) {
            if (gmp_cmp(gmp_add($y1, $y2), $p) === 0) return null; // y1 + y2 = p => y2 = -y1
            return self::pointDouble($p1);
        }
        $slope = self::modInv(gmp_sub($x2, $x1), $p);
        $slope = self::mod(gmp_mul($slope, gmp_sub($y2, $y1)), $p);
        $x3 = self::mod(gmp_sub(gmp_sub(gmp_mul($slope, $slope), $x1), $x2), $p);
        $y3 = self::mod(gmp_sub(gmp_mul($slope, gmp_sub($x1, $x3)), $y1), $p);
        return [$x3, $y3];
    }

    public static function pointDouble($point) {
        if ($point === null) return null;
        list($x1, $y1) = $point;
        $p = self::p();
        if (gmp_cmp($y1, 0) === 0) return null;
        $num = self::mod(gmp_mul(3, gmp_mul($x1, $x1)), $p);
        $den = self::modInv(gmp_mul(2, $y1), $p);
        $slope = self::mod(gmp_mul($num, $den), $p);
        $x3 = self::mod(gmp_sub(gmp_mul($slope, $slope), gmp_mul(2, $x1)), $p);
        $y3 = self::mod(gmp_sub(gmp_mul($slope, gmp_sub($x1, $x3)), $y1), $p);
        return [$x3, $y3];
    }

    public static function pointMul($k, $point) {
        $k = self::mod($k, self::n());
        if (gmp_cmp($k, 0) === 0) return null;
        $addend = $point;
        $result = null;
        while (gmp_cmp($k, 0) > 0) {
            if (gmp_testbit($k, 0)) {
                $result = self::pointAdd($result, $addend);
            }
            $addend = self::pointDouble($addend);
            $k = gmp_div_q($k, 2);
        }
        return $result;
    }

    // Multiply generator by scalar
    public static function scalarBaseMul($k) {
        return self::pointMul($k, [self::gx(), self::gy()]);
    }

    // Ripple signing: sha512 half (first 32 bytes of sha512)
    public static function sha512Half($data) {
        return substr(hash('sha512', $data, true), 0, 32);
    }

    // RFC6979 deterministic nonce (HMAC-SHA256), matching @noble/curves
    public static function rfc6979K($msgHash, $privKeyBytes) {
        $N = self::n();
        $x = str_pad($privKeyBytes, 32, "\x00", STR_PAD_LEFT);
        $z = gmp_mod(gmp_init(bin2hex($msgHash), 16), $N);
        $h1 = str_pad(gmp_export($z), 32, "\x00", STR_PAD_LEFT);
        $v = str_repeat("\x01", 32);
        $k = str_repeat("\x00", 32);
        $k = hash_hmac('sha256', $v . "\x00" . $x . $h1, $k, true);
        $v = hash_hmac('sha256', $v, $k, true);
        $k = hash_hmac('sha256', $v . "\x01" . $x . $h1, $k, true);
        $v = hash_hmac('sha256', $v, $k, true);
        while (true) {
            $v = hash_hmac('sha256', $v, $k, true);
            $kNum = gmp_init(bin2hex($v), 16);
            if (gmp_cmp($kNum, 1) >= 0 && gmp_cmp($kNum, $N) < 0) {
                return $kNum;
            }
        }
    }

    // Deterministic ECDSA sign (low-s normalized), returns r, s (gmp) and recid
    public static function ecdsaSign($msgHash, $privKeyBytes) {
        $N = self::n();
        $priv = gmp_init(bin2hex($privKeyBytes), 16);
        $z = gmp_mod(gmp_init(bin2hex($msgHash), 16), $N);
        while (true) {
            $k = self::rfc6979K($msgHash, $privKeyBytes);
            $p = self::scalarBaseMul($k);
            $r = gmp_mod($p[0], $N);
            if (gmp_cmp($r, 0) === 0) continue;
            $s = gmp_mod(gmp_mul(gmp_add($z, gmp_mul($r, $priv)), gmp_invert($k, $N)), $N);
            if (gmp_cmp($s, 0) === 0) continue;
            $halfN = gmp_div_q($N, 2);
            $recid = gmp_intval(gmp_mod($p[1], 2));
            if (gmp_cmp($s, $halfN) > 0) {
                $s = gmp_sub($N, $s);
                $recid ^= 1;
            }
            return ['r' => $r, 's' => $s, 'recid' => $recid];
        }
    }

    // Standard ECDSA verify: true if r == x(u1*G + u2*Q) mod N
    public static function ecdsaVerify($msgHash, $r, $s, $pubkeyBytes) {
        $N = self::n();
        $p = self::p();
        $r = gmp_mod($r, $N);
        $s = gmp_mod($s, $N);
        if (gmp_cmp($r, 1) < 0 || gmp_cmp($r, gmp_sub($N, 1)) > 0) return false;
        if (gmp_cmp($s, 1) < 0 || gmp_cmp($s, gmp_sub($N, 1)) > 0) return false;
        $z = gmp_mod(gmp_init(bin2hex($msgHash), 16), $N);
        $w = gmp_invert($s, $N);
        $u1 = gmp_mod(gmp_mul($z, $w), $N);
        $u2 = gmp_mod(gmp_mul($r, $w), $N);

        // parse public key (compressed 02/03 + x, or uncompressed 04 + x + y)
        $pub = $pubkeyBytes;
        $prefix = ord($pub[0]);
        if ($prefix === 4) {
            $x = gmp_init(bin2hex(substr($pub, 1, 32)), 16);
            $y = gmp_init(bin2hex(substr($pub, 33, 32)), 16);
        } elseif ($prefix === 2 || $prefix === 3) {
            $x = gmp_init(bin2hex(substr($pub, 1, 32)), 16);
            $y2 = gmp_mod(gmp_add(gmp_mul(gmp_mod(gmp_mul($x, $x), $p), $x), 7), $p);
            $y = gmp_powm($y2, gmp_div_q(gmp_add($p, 1), 4), $p);
            $yParity = gmp_intval(gmp_mod($y, 2));
            if ($yParity !== ($prefix - 2)) {
                $y = gmp_sub($p, $y);
            }
        } else {
            return false;
        }
        if (gmp_cmp(gmp_mod(gmp_add(gmp_mul(gmp_mod(gmp_mul($y, $y), $p), 1), 0), $p), gmp_mod(gmp_add(gmp_powm($x, 3, $p), 7), $p)) !== 0) {
            return false;
        }

        $g1 = self::scalarBaseMul($u1);
        $q = [$x, $y];
        $g2 = self::pointMul($u2, $q);
        $pt = self::pointAdd($g1, $g2);
        if ($pt === null) return false;
        return gmp_cmp(gmp_mod($pt[0], $N), $r) === 0;
    }

    // Compressed public key from scalar
    public static function privateKeyToPublicKey($privKeyHex) {
        $priv = gmp_init($privKeyHex, 16);
        $point = self::scalarBaseMul($priv);
        if ($point === null) {
            throw new Exception('Invalid private key');
        }
        list($x, $y) = $point;
        $xHex = gmp_strval($x, 16);
        $xHex = str_pad($xHex, 64, '0', STR_PAD_LEFT);
        $prefix = (gmp_intval(gmp_mod($y, 2)) === 0) ? '02' : '03';
        return $prefix . $xHex;
    }

    public static function privateKeyToPublicKeyUncompressed($privKeyHex) {
        $priv = gmp_init($privKeyHex, 16);
        $point = self::scalarBaseMul($priv);
        if ($point === null) {
            throw new Exception('Invalid private key');
        }
        list($x, $y) = $point;
        return '04' . str_pad(gmp_strval($x, 16), 64, '0', STR_PAD_LEFT) . str_pad(gmp_strval($y, 16), 64, '0', STR_PAD_LEFT);
    }
}
