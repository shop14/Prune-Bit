<?php

class Address {
    const P2PKH_VERSION = [
        'BTC' => 0x00, 'BTCT' => 0x6f, 'LTC' => 0x30, 'DOGE' => 0x1E,
        'DASH' => 0x4C, 'DGB' => 0x1E, 'RVN' => 0x3C, 'BTG' => 0x26,
        'BCH' => 0x00, 'ZEC' => 0x1C, 'BSV' => 0x00, 'XVG' => 0x1E,
        'QTUM' => 0x3A, 'VTC' => 0x47, 'KMD' => 0x3C
    ];
    const P2SH_VERSION = ['BTC' => 0x05, 'BTCT' => 0xc4, 'LTC' => 0x32, 'DGB' => 0x05];
    const KASPA_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    const KASPA_GEN = [0x98f2bc8e61, 0x79b76d99e2, 0xf33e5fb3c4, 0xae2eabe2a8, 0x1e4f43e470];
    const XRP_ALPHABET = 'rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1tuvAxyz';

    private static $coinPaths = [
        'BTC' => "m/44'/0'/0'/0/", 'BTCT' => "m/44'/1'/0'/0/", 'BCH' => "m/44'/145'/0'/0/",
        'ETH' => "m/44'/60'/0'/0/", 'POLYGON' => "m/44'/60'/0'/0/", 'BSC' => "m/44'/60'/0'/0/",
        'LTC' => "m/44'/2'/0'/0/", 'DOGE' => "m/44'/3'/0'/0/", 'DASH' => "m/44'/5'/0'/0/",
        'DGB' => "m/44'/20'/0'/0/", 'RVN' => "m/44'/175'/0'/0/", 'BTG' => "m/44'/156'/0'/0/",
        'ZEC' => "m/44'/133'/0'/0/", 'BSV' => "m/44'/236'/0'/0/", 'XVG' => "m/44'/77'/0'/0/",
        'QTUM' => "m/44'/2301'/0'/0/", 'VTC' => "m/44'/28'/0'/0/", 'KMD' => "m/44'/141'/0'/0/",
        'KASPA' => "m/44'/111111'/0'/0/", 'XRP' => "m/44'/144'/0'/0/", 'ETC' => "m/44'/61'/0'/0/"
    ];
    private static $base58Coins = ['BTC', 'BTCT', 'LTC', 'DOGE', 'DASH', 'DGB', 'RVN', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD'];

    public static function hash160($data) {
        return hash('ripemd160', hash('sha256', $data, true), true);
    }

    /**
     * BTC/BTCT/LTC/DGB multi-type addresses (legacy P2PKH, nested P2SH, native bech32).
     */
    public static function generateBitcoinStyle($mnemonic, $coin = 'BTC') {
        $coinUpper = strtoupper($coin);
        $config = [
            'BTC' => ['hrp' => 'bc',  'legacy' => "m/44'/0'/0'/0/",  'nested' => "m/49'/0'/0'/0/",  'native' => "m/84'/0'/0'/0/"],
            'BTCT' => ['hrp' => 'tb', 'legacy' => "m/44'/1'/0'/0/",  'nested' => "m/49'/1'/0'/0/",  'native' => "m/84'/1'/0'/0/"],
            'LTC' => ['hrp' => 'ltc', 'legacy' => "m/44'/2'/0'/0/",  'nested' => "m/49'/2'/0'/0/",  'native' => "m/84'/2'/0'/0/"],
            'DGB' => ['hrp' => 'dgb', 'legacy' => "m/44'/20'/0'/0/", 'nested' => null,               'native' => "m/84'/20'/0'/0/"]
        ];
        $cfg = $config[$coinUpper] ?? $config['BTC'];
        $seed = Bip39::mnemonicToSeed($mnemonic);
        $p2pkh = []; $p2sh = []; $bech32 = [];
        for ($i = 0; $i < 3; $i++) {
            if ($cfg['legacy']) {
                $p2pkh[] = self::pubkeyToP2PKH(Bip32::derivePath($cfg['legacy'] . $i, $seed)->getPublicKey(), $coinUpper);
            }
            if ($cfg['nested']) {
                $p2sh[] = self::pubkeyToP2SH(Bip32::derivePath($cfg['nested'] . $i, $seed)->getPublicKey(), $coinUpper);
            }
            if ($cfg['native']) {
                $bech32[] = self::pubkeyToBech32(Bip32::derivePath($cfg['native'] . $i, $seed)->getPublicKey(), $cfg['hrp']);
            }
        }
        return ['p2pkh' => $p2pkh, 'p2sh' => $p2sh, 'bech32' => $bech32];
    }

    public static function deriveAddressByIndex($mnemonic, $coin = 'BTC', $index = 0) {
        $coinUpper = strtoupper($coin);
        $path = (self::$coinPaths[$coinUpper] ?? "m/44'/0'/0'/0/") . $index;
        $seed = Bip39::mnemonicToSeed($mnemonic);
        $child = Bip32::derivePath($path, $seed);

        if ($coinUpper === 'KASPA') {
            return self::kaspaAddress($child->getPublicKey());
        }
        if ($coinUpper === 'XRP') {
            return self::xrpAddress($child->getPublicKey());
        }
        if (in_array($coinUpper, self::$base58Coins)) {
            $prefix = $coinUpper === 'ZEC' ? "\x1c\xb8" : chr(self::P2PKH_VERSION[$coinUpper] ?? 0x00);
            return Base58::base58CheckEncode($prefix . self::hash160(hex2bin($child->getPublicKey())));
        }
        if (in_array($coinUpper, ['ETH', 'USDT', 'POLYGON', 'BSC'])) {
            return self::ethereumAddress($mnemonic, $index, "m/44'/60'/0'/0/");
        }
        if ($coinUpper === 'ETC') {
            return self::ethereumAddress($mnemonic, $index, "m/44'/61'/0'/0/");
        }
        if ($coinUpper === 'BCH') {
            return self::bitcoinCashAddress($child->getPublicKey());
        }
        return Base58::base58CheckEncode("\x00" . self::hash160(hex2bin($child->getPublicKey())));
    }

    public static function pubkeyToP2PKH($pubkeyHex, $coin = 'BTC') {
        $coinUpper = strtoupper($coin);
        $prefixBytes = $coinUpper === 'ZEC' ? "\x1c\xb8" : chr(self::P2PKH_VERSION[$coinUpper] ?? 0x00);
        return Base58::base58CheckEncode($prefixBytes . self::hash160(hex2bin($pubkeyHex)));
    }

    public static function pubkeyToP2SH($pubkeyHex, $coin = 'BTC') {
        $hash = self::hash160(hex2bin($pubkeyHex));
        $redeemScript = "\x00\x14" . $hash;
        $redeemHash160 = self::hash160($redeemScript);
        $versionByte = chr(self::P2SH_VERSION[strtoupper($coin)] ?? 0x05);
        return Base58::base58CheckEncode($versionByte . $redeemHash160);
    }

    public static function pubkeyToBech32($pubkeyHex, $hrp = 'bc') {
        $hash = self::hash160(hex2bin($pubkeyHex));
        $words = array_merge([0], Bech32::toWords($hash));
        return Bech32::encode($hrp, $words);
    }

    public static function ethereumAddress($mnemonic, $index = 0, $coinPath = "m/44'/60'/0'/0/") {
        $seed = Bip39::mnemonicToSeed($mnemonic);
        $node = Bip32::derivePath($coinPath . $index, $seed);
        $pubU = Secp256k1::privateKeyToPublicKeyUncompressed($node->getPrivateKey());
        $keccak = Keccak::hash(hex2bin(substr($pubU, 2)), 256);
        $addr = substr(bin2hex($keccak), 24);
        return '0x' . self::eip55Checksum($addr);
    }

    public static function privateKeyToEthereumAddress($privateKeyHex) {
        $pubU = Secp256k1::privateKeyToPublicKeyUncompressed($privateKeyHex);
        $keccak = Keccak::hash(hex2bin(substr($pubU, 2)), 256);
        $addr = substr(bin2hex($keccak), 24);
        return '0x' . self::eip55Checksum($addr);
    }

    public static function kaspaAddress($pubkeyHex) {
        $x = substr($pubkeyHex, 2, 64); // 32-byte X coordinate
        $payload8 = "\x00" . hex2bin($x);
        $payload5 = self::convertBits(array_values(unpack('C*', $payload8)), 8, 5, true);
        return self::kaspaBech32Encode('kaspa', $payload5);
    }

    public static function xrpAddress($pubkeyHex) {
        $hash = self::hash160(hex2bin($pubkeyHex));
        return Base58::base58CheckEncodeWithAlphabet("\x00" . $hash, self::XRP_ALPHABET);
    }

    public static function bitcoinCashAddress($pubkeyHex) {
        $legacy = self::pubkeyToP2PKH($pubkeyHex, 'BCH');
        $decoded = Base58::base58CheckDecode($legacy);
        $hash = substr($decoded, 1); // strip version byte 0x00
        $versionByte = 0x00; // P2PKH, 20-byte hash => (0 << 3) | 0
        $payload8 = chr($versionByte) . $hash;
        $payload5 = self::convertBits(array_values(unpack('C*', $payload8)), 8, 5, true);
        return self::kaspaBech32Encode('bitcoincash', $payload5);
    }

    // --- internal helpers ---

    private static function eip55Checksum($addrHex) {
        $h = bin2hex(Keccak::hash(strtolower($addrHex), 256));
        $out = '';
        for ($i = 0; $i < 40; $i++) {
            $c = $addrHex[$i];
            if (ctype_alpha($c)) {
                $out .= (hexdec($h[$i]) >= 8) ? strtoupper($c) : strtolower($c);
            } else {
                $out .= $c;
            }
        }
        return $out;
    }

    public static function convertBits($data, $fromBits, $toBits, $pad = true) {
        $acc = 0; $bits = 0; $ret = [];
        $maxv = (1 << $toBits) - 1;
        foreach ($data as $d) {
            $acc = ($acc << $fromBits) | $d;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
                $acc &= (1 << $bits) - 1;
            }
        }
        if ($pad && $bits > 0) {
            $ret[] = ($acc << ($toBits - $bits)) & $maxv;
        }
        return $ret;
    }

    public static function kaspaPolymod($values) {
        $c = 1;
        foreach ($values as $v) {
            $c0 = intdiv($c, 0x800000000);
            $c = self::xor40(($c % 0x800000000) * 32, $v);
            if ($c0 & 1) $c = self::xor40($c, self::KASPA_GEN[0]);
            if ($c0 & 2) $c = self::xor40($c, self::KASPA_GEN[1]);
            if ($c0 & 4) $c = self::xor40($c, self::KASPA_GEN[2]);
            if ($c0 & 8) $c = self::xor40($c, self::KASPA_GEN[3]);
            if ($c0 & 16) $c = self::xor40($c, self::KASPA_GEN[4]);
        }
        return self::xor40($c, 1);
    }

    private static function xor40($a, $b) {
        $aLow = $a % 0x100000000;
        $aHigh = intdiv($a, 0x100000000) & 0xff;
        $bLow = $b % 0x100000000;
        $bHigh = intdiv($b, 0x100000000) & 0xff;
        return (($aHigh ^ $bHigh) << 32) | ($aLow ^ $bLow);
    }

    private static function kaspaBech32Encode($prefix, $payload5) {
        $prefixBytes = [];
        for ($i = 0; $i < strlen($prefix); $i++) {
            $prefixBytes[] = ord($prefix[$i]) & 0x1f;
        }
        $checksumInput = array_merge($prefixBytes, [0], $payload5, [0, 0, 0, 0, 0, 0, 0, 0]);
        $polymod = self::kaspaPolymod($checksumInput);
        $checksum = [];
        for ($i = 0; $i < 8; $i++) {
            $checksum[] = intdiv($polymod, 32 ** (7 - $i)) % 32;
        }
        $all = array_merge($payload5, $checksum);
        $encoded = '';
        foreach ($all as $v) {
            $encoded .= self::KASPA_CHARSET[$v];
        }
        return $prefix . ':' . $encoded;
    }
}
