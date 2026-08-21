<?php

spl_autoload_register(function ($class) {
    $prefix = 'classes\\';
    if (strpos($class, $prefix) === 0) {
        $class = substr($class, strlen($prefix));
    }
    $paths = [
        __DIR__ . '/classes/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

require_once __DIR__ . '/classes/Base58.php';
require_once __DIR__ . '/classes/Bech32.php';
require_once __DIR__ . '/classes/Keccak.php';
require_once __DIR__ . '/classes/Secp256k1.php';
require_once __DIR__ . '/classes/Bip39.php';
require_once __DIR__ . '/classes/Bip32.php';
require_once __DIR__ . '/classes/Encryption.php';
require_once __DIR__ . '/classes/Address.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Session.php';
require_once __DIR__ . '/classes/Wallet.php';
require_once __DIR__ . '/classes/Transaction.php';
require_once __DIR__ . '/classes/RateLimitStore.php';
require_once __DIR__ . '/classes/BanList.php';
require_once __DIR__ . '/classes/Unblock.php';
require_once __DIR__ . '/classes/QRCode.php';
require_once __DIR__ . '/classes/Captcha.php';
require_once __DIR__ . '/classes/TransactionBuilder.php';
require_once __DIR__ . '/classes/QRCode.php';
