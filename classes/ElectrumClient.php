<?php

class ElectrumClient {
    const DEFAULT_SERVERS = [
        'BTC' => [
            ['host' => 'electrum.blockstream.info', 'port' => 50002],
            ['host' => 'electrum.emzy.de', 'port' => 50002],
            ['host' => 'electrum.bitaroo.net', 'port' => 50002],
            ['host' => 'fulcrum.criptolayer.net', 'port' => 50002],
            ['host' => 'electrum.bitcoin.geniaku.com', 'port' => 50002],
            ['host' => 'electrum.bitkoin.zone', 'port' => 50002],
            ['host' => 'electrum3.bluewallet.io', 'port' => 50002],
            ['host' => 'electrum1.bluewallet.io', 'port' => 50002],
            ['host' => 'e.keff.org', 'port' => 50002],
            ['host' => 'electrum.ethibox.fr', 'port' => 50002],
            ['host' => 'hsmiths.com', 'port' => 50002],
            ['host' => 'electrum.qtornado.com', 'port' => 50002],
            ['host' => 'bitcoin.lovelight.io', 'port' => 50002],
        ],
        'LTC' => [
            ['host' => 'electrum.ltc.xurious.com', 'port' => 50002],
            ['host' => 'ltc.aftrek.org', 'port' => 50002],
            ['host' => 'backup.electrum-ltc.org', 'port' => 50002],
            ['host' => 'electrum-ltc.bysh.me', 'port' => 50002],
            ['host' => 'electrum.ltc.quebecois.com', 'port' => 50002],
            ['host' => 'electrum-ltc.wright.hu', 'port' => 50002],
            ['host' => 'electrum-ltc.privatepay.cash', 'port' => 50002],
            ['host' => 'electrum-ltc.w014l0.xyz', 'port' => 50002],
            ['host' => 'ltc.radix.codes', 'port' => 50002],
            ['host' => 'liteserver.xyz', 'port' => 50002],
        ],
    ];

    const MONITOR_URLS = [
        'BTC' => 'https://1209k.com/bitcoin-eye/ele.php?chain=btc',
        'LTC' => 'https://1209k.com/bitcoin-eye/ele.php?chain=ltc',
    ];

    private $coin;
    private $servers;
    private $failedHosts = [];
    private static $serverCache = [];
    private static $tcpAvailable = null;

    public function __construct($coin = 'BTC') {
        $this->coin = strtoupper($coin);
        $this->servers = self::DEFAULT_SERVERS[$this->coin] ?? self::DEFAULT_SERVERS['BTC'];
    }

    public static function isAvailable() {
        return self::canUseTcpSockets();
    }

    private static function canUseTcpSockets() {
        if (self::$tcpAvailable !== null) return self::$tcpAvailable;
        try {
            $fp = @stream_socket_client('tls://electrum.blockstream.info:50002', $errno, $errstr, 2, STREAM_CLIENT_CONNECT);
            if ($fp !== false) { fclose($fp); self::$tcpAvailable = true; return true; }
        } catch (Throwable $e) {}
        self::$tcpAvailable = false;
        return false;
    }

    private function fetchServers() {
        $upper = $this->coin;
        if (isset(self::$serverCache[$upper]) && (time() - self::$serverCache[$upper]['ts']) < 600) {
            return self::$serverCache[$upper]['servers'];
        }
        $url = self::MONITOR_URLS[$upper] ?? null;
        if (!$url) return self::DEFAULT_SERVERS[$upper] ?? [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $html = curl_exec($ch);
        $ok = curl_errno($ch) === 0;
        curl_close($ch);
        if (!$ok || !$html) return self::DEFAULT_SERVERS[$upper] ?? [];

        preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $rowMatches);
        $candidates = [];
        foreach ($rowMatches[1] as $row) {
            preg_match_all('/<td>(.*?)<\/td>/', $row, $cellMatches);
            $cells = $cellMatches[1];
            if (count($cells) < 3) continue;
            $host = trim(strip_tags($cells[0]));
            $port = (int)trim(strip_tags($cells[1]));
            $proto = trim(strip_tags($cells[2]));
            $status = count($cells) > 10 ? trim(strip_tags($cells[10])) : '';
            $uptimeDay = count($cells) > 12 ? (float)trim(strip_tags($cells[12])) : 0;
            if (strpos($host, '.onion') !== false) continue;
            if ($proto !== 'ssl' || $status !== 'OK') continue;
            if ($port < 1) continue;
            $candidates[] = ['host' => $host, 'port' => $port, 'uptimeDay' => $uptimeDay];
        }
        usort($candidates, function ($a, $b) { return $b['uptimeDay'] <=> $a['uptimeDay']; });
        $topServers = array_slice($candidates, 0, 3);
        $healthy = [];
        foreach ($topServers as $s) {
            if ($this->testServer($s)) $healthy[] = ['host' => $s['host'], 'port' => $s['port']];
        }
        if (count($healthy) >= 2) {
            self::$serverCache[$upper] = ['servers' => $healthy, 'ts' => time()];
            return $healthy;
        }
        $defaultHealthy = [];
        foreach (self::DEFAULT_SERVERS[$upper] ?? [] as $s) {
            if ($this->testServer($s)) $defaultHealthy[] = $s;
        }
        $mixed = array_merge($healthy, $defaultHealthy);
        $seen = [];
        $result = [];
        foreach ($mixed as $s) {
            $key = $s['host'] . ':' . $s['port'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $result[] = $s;
            if (count($result) >= 5) break;
        }
        if (count($result) >= 2) self::$serverCache[$upper] = ['servers' => $result, 'ts' => time()];
        return $result;
    }

    public function discoverServers() {
        $this->failedHosts = [];
        if (!self::canUseTcpSockets()) {
            $this->servers = [];
            return;
        }
        try {
            $this->servers = $this->fetchServers();
        } catch (Throwable $e) {
            error_log('Electrum server discovery failed for ' . $this->coin . ': ' . $e->getMessage());
        }
    }

    private function testServer($server) {
        return $this->connectAndRequest($server, 'server.version', [], 3) !== false;
    }

    private function addressToScripthash($address) {
        $script = $this->addressToOutputScript($address);
        if ($script === null) {
            throw new Exception('Unsupported address format: ' . $address);
        }
        return strrev(hash('sha256', $script, true));
    }

    private function addressToOutputScript($address) {
        $coinUpper = $this->coin;
        $bech32Hrp = $coinUpper === 'BTCT' ? 'tb' : ($coinUpper === 'LTC' ? 'ltc' : ($coinUpper === 'DGB' ? 'dgb' : 'bc'));
        if (strlen($address) > 20 && preg_match('/^(bc|tb|ltc|dgb)1/', $address)) {
            $decoded = Bech32::decode($address);
            if ($decoded === null || !isset($decoded['data'])) return null;
            $data = $decoded['data'];
            if (count($data) < 2) return null;
            $program = Bech32::fromWords(array_slice($data, 1));
            if ($program === null) return null;
            if (count($program) === 20) return "\x00\x14" . implode('', array_map('chr', $program));
            if (count($program) === 32) return "\x00\x20" . implode('', array_map('chr', $program));
            return null;
        }

        try {
            $decoded = Base58::base58CheckDecode($address);
        } catch (Throwable $e) {
            return null;
        }
        $versionByte = ord($decoded[0]);
        $p2pkh = Address::P2PKH_VERSION[$coinUpper] ?? 0x00;
        $p2sh = Address::P2SH_VERSION[$coinUpper] ?? null;
        $hash = substr($decoded, 1);
        if ($versionByte === $p2pkh && strlen($hash) === 20) {
            return "\x76\xa9\x14" . $hash . "\x88\xac";
        }
        if ($p2sh !== null && $versionByte === $p2sh && strlen($hash) === 20) {
            return "\xa9\x14" . $hash . "\x87";
        }
        if ($coinUpper === 'ZEC' && strlen($decoded) === 22 && ord($decoded[0]) === 0x1c && ord($decoded[1]) === 0xb8) {
            $hash = substr($decoded, 2);
            return "\x76\xa9\x14" . $hash . "\x88\xac";
        }
        return null;
    }

    private function connectAndRequest($server, $method, $params, $timeout = 4) {
        $errno = null;
        $errstr = null;
        $host = $server['host'];
        $port = $server['port'];
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $fp = @stream_socket_client('tls://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($fp === false) {
            throw new Exception('Connection failed: ' . $errstr);
        }
        stream_set_timeout($fp, $timeout);
        $id = uniqid('', true);
        $req = json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]) . "\n";
        $written = fwrite($fp, $req);
        if ($written === false) {
            fclose($fp);
            throw new Exception('Write failed');
        }
        $buf = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $chunk = fread($fp, 4096);
            if ($chunk === false) break;
            if ($chunk === '') {
                if (feof($fp)) break;
                usleep(100000);
                continue;
            }
            $buf .= $chunk;
            if (strpos($buf, "\n") !== false) break;
        }
        fclose($fp);
        $lines = explode("\n", $buf);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $resp = json_decode($line, true);
            if (!is_array($resp)) continue;
            if (($resp['id'] ?? null) !== $id) continue;
            if (isset($resp['error']) && $resp['error'] !== null) {
                throw new Exception($resp['error']['message'] ?? json_encode($resp['error']));
            }
            return $resp['result'] ?? null;
        }
        throw new Exception('Electrum timeout');
    }

    private function request($method, $params, $retries = 1) {
        if (!self::canUseTcpSockets() || empty($this->servers)) {
            throw new Exception('Electrum unavailable (TCP sockets blocked on this hosting)');
        }
        try {
            return $this->tryServers($this->servers, $method, $params, $retries);
        } catch (Throwable $e) {
            unset(self::$serverCache[$this->coin]);
            throw $e;
        }
    }

    public function estimateFee($numBlocks, $retries = 1) {
        return $this->request('blockchain.estimatefee', [$numBlocks], $retries);
    }

    private function tryServers($serverList, $method, $params, $retries) {
        $lastErr = null;
        foreach ($serverList as $server) {
            if (isset($this->failedHosts[$server['host']])) continue;
            for ($i = 0; $i <= $retries; $i++) {
                try {
                    return $this->connectAndRequest($server, $method, $params);
                } catch (Throwable $e) {
                    $lastErr = $e;
                }
            }
            $this->failedHosts[$server['host']] = true;
        }
        throw $lastErr ?: new Exception('Electrum ' . $this->coin . ': all servers failed for ' . $method);
    }

    public function getBalance($address) {
        $sh = $this->addressToScripthash($address);
        $result = $this->request('blockchain.scripthash.get_balance', [$sh]);
        return [
            'balance' => ($result['confirmed'] ?? 0) / 1e8,
            'unconfirmed_balance' => ($result['unconfirmed'] ?? 0) / 1e8,
            'address' => $address,
        ];
    }

    public function getTransactions($address) {
        $sh = $this->addressToScripthash($address);
        $history = $this->request('blockchain.scripthash.get_history', [$sh]);
        if (!$history || count($history) === 0) return [];
        $recent = array_slice($history, -15);
        $results = [];
        foreach ($recent as $h) {
            try {
                $verbose = $this->request('blockchain.transaction.get', [$h['tx_hash'], true]);
                if ($verbose === null) continue;
                $info = $this->buildTxInfo($verbose, $address);
                $results[] = $info;
            } catch (Throwable $e) {
                continue;
            }
        }
        return $results;
    }

    private function buildTxInfo($verbose, $address) {
        $addrLower = strtolower($address);
        $received = 0;
        $recipients = [];
        $vouts = $verbose['vout'] ?? [];
        foreach ($vouts as $out) {
            $addr = $out['scriptpubkey_address'] ?? $this->addressFromScript($out['scriptpubkey'] ?? '');
            if ($addr !== null) {
                if (strtolower($addr) === $addrLower) {
                    $received += (float)($out['value'] ?? 0);
                } else {
                    $recipients[] = $addr;
                }
            }
        }
        $senders = [];
        $isSender = false;
        $vins = $verbose['vin'] ?? [];
        foreach ($vins as $in) {
            $addr = $in['prevout']['scriptpubkey_address'] ?? null;
            if ($addr === null) continue;
            if (strtolower($addr) === $addrLower) {
                $isSender = true;
            } else {
                $senders[] = $addr;
            }
        }
        $senders = array_values(array_unique($senders));

        if ($isSender) {
            $totalOut = 0;
            foreach ($vouts as $out) $totalOut += (float)($out['value'] ?? 0);
            $value = -($totalOut - $received);
            $fromAddress = $address;
            $toAddress = count($recipients) > 0 ? $recipients[0] : null;
        } else {
            $value = $received;
            $fromAddress = count($senders) > 0 ? $senders[0] : null;
            $toAddress = $address;
        }

        $timestamp = $verbose['time'] ?? $verbose['blocktime'] ?? time();
        $confirmations = $verbose['confirmations'] ?? 0;
        $fee = 0;
        if (isset($verbose['fee'])) {
            $fee = (float)$verbose['fee'] / 1e8;
        } elseif (count($vins) > 0 && count($vouts) > 0) {
            $totalIn = 0;
            foreach ($vins as $v) $totalIn += (float)($v['prevout']['value'] ?? 0);
            $totalOut = 0;
            foreach ($vouts as $v) $totalOut += (float)($v['value'] ?? 0);
            if ($totalIn > 0) $fee = $totalIn - $totalOut;
        }

        return [
            'tx_hash' => $verbose['txid'] ?? '',
            'confirmations' => $confirmations,
            'timestamp' => $timestamp,
            'value' => $value / 1e8,
            'fee' => $fee,
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
        ];
    }

    private function addressFromScript($scriptHex) {
        if (!is_string($scriptHex) || $scriptHex === '') return null;
        $script = hex2bin($scriptHex);
        if ($script === false) return null;
        $len = strlen($script);
        $coinUpper = $this->coin;
        try {
            if ($len === 25 && bin2hex($script[0]) === '76' && bin2hex($script[1]) === 'a9' && bin2hex($script[$len - 2]) === 'ac') {
                $hash = substr($script, 3, 20);
                $prefix = $coinUpper === 'ZEC' ? "\x1c\xb8" : chr(Address::P2PKH_VERSION[$coinUpper] ?? 0x00);
                return Base58::base58CheckEncode($prefix . $hash);
            }
            if ($len === 23 && bin2hex($script[0]) === 'a9' && bin2hex($script[$len - 1]) === '87') {
                $hash = substr($script, 2, 20);
                $versionByte = chr(Address::P2SH_VERSION[$coinUpper] ?? 0x05);
                return Base58::base58CheckEncode($versionByte . $hash);
            }
            if ($len === 22 && bin2hex($script[0]) === '00' && bin2hex($script[1]) === '14') {
                $program = substr($script, 2, 20);
                $words = Bech32::toWords(array_values(unpack('C*', $program)));
                $hrp = $coinUpper === 'BTCT' ? 'tb' : ($coinUpper === 'LTC' ? 'ltc' : ($coinUpper === 'DGB' ? 'dgb' : 'bc'));
                return Bech32::encode($hrp, array_merge([0], $words));
            }
            if ($len === 34 && bin2hex($script[0]) === '00' && bin2hex($script[1]) === '20') {
                $program = substr($script, 2, 32);
                $words = Bech32::toWords(array_values(unpack('C*', $program)));
                $hrp = $coinUpper === 'BTCT' ? 'tb' : ($coinUpper === 'LTC' ? 'ltc' : ($coinUpper === 'DGB' ? 'dgb' : 'bc'));
                return Bech32::encode($hrp, array_merge([0], $words));
            }
            if ($len === 35 && (bin2hex($script[0]) === '21' || bin2hex($script[0]) === '41') && bin2hex($script[$len - 1]) === 'ac') {
                $pubkey = substr($script, 1, $len - 2);
                return Address::pubkeyToP2PKH($pubkey, $coinUpper);
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }

    public function getUTXOs($address) {
        $sh = $this->addressToScripthash($address);
        $result = $this->request('blockchain.scripthash.listunspent', [$sh]);
        $utxos = [];
        foreach ($result ?? [] as $utxo) {
            $utxos[] = [
                'txid' => $utxo['tx_hash'],
                'vout' => $utxo['tx_pos'],
                'value' => $utxo['value'],
                'status' => ['confirmed' => ($utxo['height'] ?? 0) > 0],
            ];
        }
        return $utxos;
    }

    public function broadcast($rawTxHex) {
        $result = $this->request('blockchain.transaction.broadcast', [$rawTxHex]);
        return ['tx' => ['hash' => $result], 'confirmed' => false];
    }
}
