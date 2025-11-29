<?php
// General app configuration

define('DB_HOST', 'localhost');
define('DB_NAME', 'ejustice_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// Encryption
define('DOC_ENC_METHOD', 'AES-256-CBC');
// CHANGE THIS TO A LONG RANDOM STRING BEFORE USING IN PRODUCTION
define('DOC_ENC_KEY', hash('sha256', 'CHANGE_ME_SUPER_SECRET_KEY'));
