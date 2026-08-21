<?php

/**
 * API keys — example file
 * --------------------------------------------------
 * Copy this file to api_keys.php and fill in your keys.
 * All keys are optional; features degrade gracefully when empty.
 * api_keys.php is git-ignored and must never be committed.
 */

return [
    // BlockCypher — BTC/BTC-family balance & tx APIs (3 keys for rotation)
    'BLOCKCYPHER_API_KEY_1' => '',
    'BLOCKCYPHER_API_KEY_2' => '',
    'BLOCKCYPHER_API_KEY_3' => '',

    // Etherscan — Ethereum & ERC-20 data
    'ETHERSCAN_API_KEY' => '',

    // CryptoAPIs — fee estimation
    'CRYPTOAPIS_API_KEY' => '',

    // ChangeNOW — instant exchange integration
    'CHANGENOW_API_KEY' => '',

    // Tatum — tx relaying / raw tx fetch (optional)
    'TATUM_API_KEY' => '',

    // Blockchair — multi-coin balance/tx/fees
    'BLOCKCHAIR_API_KEY' => '',

    // Blockscout instances — usually no key needed
    'BLOCKSCOUT_API_KEY' => '',

    // CoinGecko — prices (rate-limited without a key)
    'COINGECKO_API_KEY' => '',
];
