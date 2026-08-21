<?php

class Bech32 {
    const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    const GENERATOR = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];

    public static function polymod($values) {
        $chk = 1;
        foreach ($values as $value) {
            $top = ($chk >> 25) & 0x1f;
            $chk = (($chk & 0x1ffffff) << 5) ^ $value;
            for ($j = 0; $j < 5; $j++) {
                if (($top >> $j) & 1) {
                    $chk ^= self::GENERATOR[$j];
                }
            }
        }
        return $chk & 0xffffffff;
    }

    public static function hrpExpand($hrp) {
        $ret = [];
        for ($i = 0; $i < strlen($hrp); $i++) {
            $ret[] = ord($hrp[$i]) >> 5;
        }
        $ret[] = 0;
        for ($i = 0; $i < strlen($hrp); $i++) {
            $ret[] = ord($hrp[$i]) & 31;
        }
        return $ret;
    }

    public static function createChecksum($hrp, $data) {
        $values = array_merge(self::hrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $polymod = self::polymod($values) ^ 1;
        $checksum = [];
        for ($i = 0; $i < 6; $i++) {
            $checksum[] = ($polymod >> (5 * (5 - $i))) & 31;
        }
        return $checksum;
    }

    public static function encode($hrp, $data) {
        $combined = array_merge($data, self::createChecksum($hrp, $data));
        $result = $hrp . '1';
        foreach ($combined as $v) {
            $result .= self::CHARSET[$v];
        }
        return $result;
    }

    public static function toWords($bytes) {
        if (is_string($bytes)) {
            $bytes = array_values(unpack('C*', $bytes));
        }
        $ret = [];
        $acc = 0;
        $bits = 0;
        $maxv = (1 << 5) - 1;
        foreach ($bytes as $value) {
            $acc = ($acc << 8) | $value;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }
        if ($bits > 0) {
            $ret[] = ($acc << (5 - $bits)) & $maxv;
        }
        return $ret;
    }

    public static function convertBits($data, $fromBits, $toBits, $pad = true) {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        foreach ($data as $value) {
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }
        if ($pad && $bits > 0) {
            $ret[] = ($acc << ($toBits - $bits)) & $maxv;
        }
        return $ret;
    }

    public static function fromWords($words) {
        $ret = [];
        $acc = 0;
        $bits = 0;
        foreach ($words as $value) {
            $acc = ($acc << 5) | $value;
            $bits += 5;
            while ($bits >= 8) {
                $bits -= 8;
                $ret[] = ($acc >> $bits) & 0xff;
            }
        }
        if ($bits >= 5 || (($acc << (8 - $bits)) & 0xff) !== 0) {
            return null;
        }
        return $ret;
    }

    public static function decode($bech32) {
        $bech32 = strtolower($bech32);
        $pos = strrpos($bech32, '1');
        if ($pos === false || $pos < 1 || $pos + 7 > strlen($bech32)) return null;
        $hrp = substr($bech32, 0, $pos);
        $data = substr($bech32, $pos + 1);
        $dataLen = strlen($data);
        $values = [];
        for ($i = 0; $i < $dataLen; $i++) {
            $c = $data[$i];
            $idx = strpos(self::CHARSET, $c);
            if ($idx === false) return null;
            $values[] = $idx;
        }
        $check = self::polymod(array_merge(self::hrpExpand($hrp), $values));
        if ($check !== 1) return null;
        $payload = array_slice($values, 0, $dataLen - 6);
        return ['hrp' => $hrp, 'data' => $payload];
    }
}
