<?php

require_once __DIR__ . '/_explorer_helpers.php';

$path = isset($GLOBALS['api_path']) ? (string) $GLOBALS['api_path'] : '';
$action = isset($_GET['a']) ? strtolower((string) $_GET['a']) : '';
if ($action === '') {
    if (strpos($path, '/bitcoin/blocks') !== false) $action = 'bitcoin:blocks';
    elseif (strpos($path, '/bitcoin/fees') !== false) $action = 'bitcoin:fees';
    elseif (strpos($path, '/bitcoin/stats') !== false) $action = 'bitcoin:stats';
    elseif (strpos($path, '/address') !== false) $action = 'address';
    elseif (strpos($path, '/tx') !== false) $action = 'tx';
    else $action = 'coins';
}

switch ($action) {
    case 'coins':
        jsonResponse(['success' => true, 'coins' => expl_coins()]);
        break;

    case 'address':
        $address = isset($_GET['address']) ? (string) $_GET['address'] : (string) (Api::body('address'));
        $coin = isset($_GET['coin']) ? (string) $_GET['coin'] : (string) (Api::body('coin'));
        if (!$address || !$coin) {
            jsonResponse(['error' => 'Address and coin are required'], 400);
        }
        $summary = expl_fetchAddressSummary($address, $coin);
        $txs = expl_fetchAddressTxs($address, $coin);
        uasort($txs, function ($a, $b) {
            $ta = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
            $tb = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
            if ($ta === $tb) return 0;
            return $ta < $tb ? 1 : -1;
        });
        $txs = array_values($txs);
        $summaryObj = $summary ? [
            'balance' => $summary['balance'],
            'unconfirmed' => isset($summary['unconfirmed']) ? $summary['unconfirmed'] : 0,
            'total_received' => isset($summary['total_received']) ? $summary['total_received'] : null,
            'total_sent' => isset($summary['total_sent']) ? $summary['total_sent'] : null,
            'tx_count' => isset($summary['tx_count']) ? $summary['tx_count'] : count($txs),
        ] : null;
        jsonResponse([
            'success' => true,
            'address' => $address,
            'coin' => strtoupper((string) $coin),
            'summary' => $summaryObj,
            'balance' => $summaryObj ? $summaryObj['balance'] : 0,
            'unconfirmed_balance' => $summaryObj ? $summaryObj['unconfirmed'] : 0,
            'total_received' => $summaryObj ? $summaryObj['total_received'] : null,
            'total_sent' => $summaryObj ? $summaryObj['total_sent'] : null,
            'tx_count' => $summaryObj ? $summaryObj['tx_count'] : count($txs),
            'transactions' => $txs,
        ]);
        break;

    case 'tx':
        $hash = isset($_GET['hash']) ? (string) $_GET['hash'] : (string) (Api::body('tx_hash'));
        $coin = isset($_GET['coin']) ? (string) $_GET['coin'] : (string) (Api::body('coin'));
        if (!$hash || !$coin) {
            jsonResponse(['error' => 'Hash and coin are required'], 400);
        }
        try {
            $detail = expl_fetchTxDetail($hash, $coin);
            if ($detail === null) {
                jsonResponse(['success' => false, 'error' => 'Transaction not found'], 404);
            } else {
                $txUrl = isset($detail['txUrl']) ? $detail['txUrl'] : '';
                unset($detail['txUrl']);
                $detail['success'] = true;
                jsonResponse(['success' => true, 'transaction' => $detail, 'explorer_url' => $txUrl]);
            }
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Explorer service temporarily unavailable'], 500);
        }
        break;

    case 'bitcoin:blocks':
        $limit = isset($_GET['limit']) ? max(1, min(25, (int) $_GET['limit'])) : 10;
        $height = isset($_GET['start_height']) ? max(0, (int) $_GET['start_height']) : 0;
        try {
            $blocks = expl_fetchBitcoinBlocks($height, $limit);
            jsonResponse(['success' => true, 'start_height' => $height, 'blocks' => $blocks]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Explorer service temporarily unavailable'], 500);
        }
        break;

    case 'bitcoin:fees':
        try {
            $r = httpRequest('https://mempool.space/api/v1/fees/recommended', 'GET');
            $f = ($r !== null && is_array($r['json'])) ? $r['json'] : [];
            $fees = [
                'fastestFee' => isset($f['fastestFee']) ? (float) $f['fastestFee'] : 15,
                'halfHourFee' => isset($f['halfHourFee']) ? (float) $f['halfHourFee'] : 8,
                'hourFee' => isset($f['hourFee']) ? (float) $f['hourFee'] : 5,
                'economyFee' => isset($f['economyFee']) ? (float) $f['economyFee'] : 3,
                'minimumFee' => isset($f['minimumFee']) ? (float) $f['minimumFee'] : 1,
            ];
            jsonResponse(['success' => true, 'fees' => $fees]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Explorer service temporarily unavailable'], 500);
        }
        break;

    case 'bitcoin:stats':
        try {
            $stats = expl_fetchBitcoinStats();
            jsonResponse(['success' => true, 'stats' => $stats]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Explorer service temporarily unavailable'], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
