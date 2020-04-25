<?php

define("PORT", 9000);
define("LOG_PATH",  __DIR__ . "/logs/server.log");
define("CERT_CA",  "path to your cert");
define("CERT_KEY", "path to you private key");
define("ROOM_HASH_LENGTH", 32);
define("ROOM_MAX_CLIENTS", 40);

// Private key to store room passwords.
// Generate it using sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))
define("KEYCHAIN_PK", "");
