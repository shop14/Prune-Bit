<?php
require 'autoload.php';
require 'config/config.php';
try {
    \Database::execute('UPDATE admin_sessions SET last_activity = NOW() WHERE token = ?', ['7bb1a0a4aeb822ef9cdd75d5adf228b02843ac4729d6c38a3981028c8a89584c']);
    echo 'Updated last_activity to NOW';
} catch(Throwable $e) { echo 'Error: ' . $e->getMessage(); }