<?php

class Keccak {
    private static $rc = [
        '0000000000000001', '0000000000008082', '800000000000808a', '8000000080008000',
        '000000000000808b', '0000000080000001', '8000000080008081', '8000000000008009',
        '000000000000008a', '0000000000000088', '0000000080008009', '000000008000000a',
        '000000008000808b', '800000000000008b', '8000000000008089', '8000000000008003',
        '8000000000008002', '8000000000000080', '000000000000800a', '800000008000000a',
        '8000000080008081', '8000000000008080', '0000000080000001', '8000000080008008'
    ];

    private static function mask64() {
        return gmp_sub(gmp_pow(2, 64), 1);
    }

    private static function rotl64($x, $n) {
        $n &= 63;
        if ($n === 0) return $x;
        $mask = self::mask64();
        $left = gmp_and(gmp_mul($x, gmp_pow(2, $n)), $mask);
        $right = gmp_and(gmp_div_q($x, gmp_pow(2, 64 - $n)), $mask);
        return gmp_and(gmp_or($left, $right), $mask);
    }

    private static function keccakF($st) {
        for ($round = 0; $round < 24; $round++) {
            // Theta
            $bc = [];
            for ($i = 0; $i < 5; $i++) {
                $bc[$i] = gmp_xor(gmp_xor(gmp_xor(gmp_xor($st[$i], $st[$i + 5]), $st[$i + 10]), $st[$i + 15]), $st[$i + 20]);
            }
            for ($i = 0; $i < 5; $i++) {
                $t = gmp_xor($bc[($i + 4) % 5], self::rotl64($bc[($i + 1) % 5], 1));
                for ($j = 0; $j < 25; $j += 5) {
                    $st[$j + $i] = gmp_xor($st[$j + $i], $t);
                }
            }

            // Rho and Pi: B[y][(2x+3y) mod 5] = ROTL(A[x][y], r[x][y])
            $rho = [
                [0, 36, 3, 41, 18],
                [1, 44, 10, 45, 2],
                [62, 6, 43, 15, 61],
                [28, 55, 25, 21, 56],
                [27, 20, 39, 8, 14]
            ];
            $newSt = [];
            for ($x = 0; $x < 5; $x++) {
                for ($y = 0; $y < 5; $y++) {
                    $yPrime = (2 * $x + 3 * $y) % 5;
                    $newSt[$y + 5 * $yPrime] = self::rotl64($st[$x + 5 * $y], $rho[$x][$y]);
                }
            }
            $st = $newSt;

            // Chi
            for ($j = 0; $j < 25; $j += 5) {
                $bc = [];
                for ($i = 0; $i < 5; $i++) {
                    $bc[$i] = $st[$j + $i];
                }
                for ($i = 0; $i < 5; $i++) {
                    $st[$j + $i] = gmp_xor($bc[$i], gmp_and(gmp_xor($bc[($i + 1) % 5], self::mask64()), $bc[($i + 2) % 5]));
                }
            }

            // Iota
            $st[0] = gmp_xor($st[0], gmp_init(self::$rc[$round], 16));
        }
        return $st;
    }

    public static function hash($input, $bitLength = 256) {
        $rate = 200 - ($bitLength >> 2);
        $input = self::pad10star1($input, $rate);
        $st = [];
        for ($i = 0; $i < 25; $i++) {
            $st[$i] = gmp_init(0);
        }
        $blocks = strlen($input) / $rate;
        for ($b = 0; $b < $blocks; $b++) {
            $block = substr($input, $b * $rate, $rate);
            for ($i = 0; $i < $rate / 8; $i++) {
                $bytes = substr($block, $i * 8, 8);
                $st[$i] = gmp_xor($st[$i], gmp_init(bin2hex(strrev($bytes)), 16));
            }
            $st = self::keccakF($st);
        }
        $out = '';
        $outLen = $bitLength / 8;
        $laneCount = $rate / 8;
        while (strlen($out) < $outLen) {
            for ($i = 0; $i < $laneCount && strlen($out) < $outLen; $i++) {
                $hex = gmp_strval($st[$i], 16);
                $hex = str_pad($hex, 16, '0', STR_PAD_LEFT);
                $out .= strrev(hex2bin($hex));
            }
            if (strlen($out) < $outLen) {
                $st = self::keccakF($st);
            }
        }
        return substr($out, 0, $outLen);
    }

    private static function pad10star1($data, $rate) {
        $padLen = ($rate - (strlen($data) % $rate) - 2 + $rate) % $rate;
        return $data . "\x01" . str_repeat("\x00", $padLen) . "\x80";
    }

    public static function hashHex($input) {
        return bin2hex(self::hash($input, 256));
    }
}
