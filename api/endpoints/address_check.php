<?php
function addressCheckPrice($coinUpper) {
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,litecoin,dogecoin,bitcoin-cash,dash,digibyte,ravencoin,bitcoin-gold,zcash,bitcoin-sv,verge,qtum,vertcoin,komodo,ethereum-classic,tether,matic-network,binancecoin,kaspa,ripple&vs_currencies=usd';
    $resp = httpRequest($url, 'GET');
    if ($resp === null || $resp['status'] !== 200 || !is_array($resp['json'])) return null;
    $data = $resp['json'];
    $coinToGecko = [
        'BTC' => 'bitcoin', 'ETH' => 'ethereum', 'LTC' => 'litecoin', 'DOGE' => 'dogecoin',
        'BCH' => 'bitcoin-cash', 'DASH' => 'dash', 'DGB' => 'digibyte', 'RVN' => 'ravencoin',
        'BTG' => 'bitcoin-gold', 'ZEC' => 'zcash', 'BSV' => 'bitcoin-sv', 'XVG' => 'verge',
        'QTUM' => 'qtum', 'VTC' => 'vertcoin', 'KMD' => 'komodo', 'ETC' => 'ethereum-classic',
        'USDT' => 'tether', 'POLYGON' => 'matic-network', 'BSC' => 'binancechain', 'KASPA' => 'kaspa', 'XRP' => 'ripple',
    ];
    $gid = $coinToGecko[$coinUpper] ?? null;
    if ($gid && isset($data[$gid]['usd'])) return $data[$gid]['usd'];
    return null;
}

$BLOCK_EXPLORERS = [
    'BTC' => 'https://mempool.space/address/',
    'BTCT' => 'https://mempool.space/testnet/address/',
    'LTC' => 'https://litecoinspace.org/address/',
    'DOGE' => 'https://dogechain.info/address/',
    'BCH' => 'https://blockchair.com/bitcoin-cash/address/',
    'ETH' => 'https://etherscan.io/address/',
    'ETC' => 'https://etc.blockscout.com/address/',
    'DASH' => 'https://blockchair.com/dash/address/',
    'DGB' => 'https://digiexplorer.info/address/',
    'RVN' => 'https://ravencoin.network/address/',
    'BTG' => 'https://btgexplorer.com/address/',
    'ZEC' => 'https://blockchair.com/zcash/address/',
    'BSV' => 'https://blockchair.com/bitcoin-sv/address/',
    'XVG' => 'https://verge-blockchain.info/address/',
    'QTUM' => 'https://qtum.info/address/',
    'VTC' => 'https://vtcexplorer.com/address/',
    'KMD' => 'https://kmdexplorer.io/address/',
    'KASPA' => 'https://kaspa.org/explorer/',
    'XRP' => 'https://xrpscan.com/account/',
    'USDT' => 'https://etherscan.io/address/',
    'POLYGON' => 'https://polygonscan.com/address/',
    'BSC' => 'https://bscscan.com/address/',
];
$SUPPORTED_COINS = ['BTC', 'ETH', 'LTC', 'DOGE', 'BCH', 'DASH', 'DGB', 'RVN', 'BTG', 'ZEC', 'BSV', 'XVG', 'QTUM', 'VTC', 'KMD', 'ETC', 'KASPA', 'XRP', 'USDT', 'POLYGON', 'BSC', 'BTCT'];

$path = $GLOBALS['api_path'] ?? '';
$isDerive = substr($path, -strlen('/derive')) === '/derive' || strpos($path, '/address_check/derive') !== false;

try {
    if ($isDerive) {
        $mnemonic = Api::body('mnemonic');
        $password = Api::body('password');
        if (!$mnemonic || !is_string($mnemonic)) {
            jsonResponse(['success' => false, 'error' => 'Invalid address provided'], 400);
        }
        if (!$password || !is_string($password)) {
            jsonResponse(['success' => false, 'error' => 'PIN required'], 400);
        }
        $coin = Api::body('coin', 'BTC');
        $coinUpper = strtoupper($coin);
        if (!in_array($coinUpper, $SUPPORTED_COINS)) {
            jsonResponse(['success' => false, 'error' => 'Unsupported coin type'], 400);
        }
        $words = preg_split('/\s+/', trim($mnemonic));
        if (!in_array(count($words), [12, 15, 18, 21, 24])) {
            jsonResponse(['success' => false, 'error' => 'Invalid mnemonic format'], 400);
        }
        $walletId = Encryption::hashMnemonic($mnemonic);
        $wallets = Database::query('SELECT * FROM wallets WHERE id = ?', [$walletId]);
        if (count($wallets) === 0) {
            jsonResponse(['success' => false, 'error' => 'Wallet not found with this mnemonic'], 404);
        }
        $wallet = $wallets[0];
        try {
            $address = Wallet::deriveAddress($wallet, $coinUpper, $password);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => 'Wrong PIN or failed to derive address'], 400);
        }
        $balanceData = BlockchainAPI::getBalance($address, $coinUpper);
        $priceUsd = addressCheckPrice($coinUpper);
        $explorer = $BLOCK_EXPLORERS[$coinUpper] ?? null;
        jsonResponse([
            'success' => true,
            'address' => $address,
            'coin' => $coinUpper,
            'balance' => $balanceData['balance'] ?? 0,
            'unconfirmed_balance' => $balanceData['unconfirmed_balance'] ?? 0,
            'price_usd' => $priceUsd,
            'explorer_url' => $explorer ? $explorer . $address : null,
        ]);
    } else {
        $address = Api::body('address');
        $coin = Api::body('coin');
        if (!$address || !is_string($address) || strlen(trim($address)) < 10) {
            jsonResponse(['success' => false, 'error' => 'Invalid address provided'], 400);
        }
        $coinUpper = ($coin ? strtoupper($coin) : 'BTC');
        if (!in_array($coinUpper, $SUPPORTED_COINS)) {
            jsonResponse(['success' => false, 'error' => 'Unsupported coin type'], 400);
        }
        $balanceData = BlockchainAPI::getBalance(trim($address), $coinUpper);
        $priceUsd = addressCheckPrice($coinUpper);
        $explorer = $BLOCK_EXPLORERS[$coinUpper] ?? null;
        jsonResponse([
            'success' => true,
            'address' => trim($address),
            'coin' => $coinUpper,
            'balance' => $balanceData['balance'] ?? 0,
            'unconfirmed_balance' => $balanceData['unconfirmed_balance'] ?? 0,
            'price_usd' => $priceUsd,
            'explorer_url' => $explorer ? $explorer . trim($address) : null,
        ]);
    }
} catch (Throwable $e) {
    error_log('address_check error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $isDerive ? 'Failed to derive and check address' : 'Failed to check address'], 500);
}
