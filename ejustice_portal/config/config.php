<?php
// General app configuration
// Supports both environment variables and hardcoded defaults

// Database configuration (reads from .env or environment variables)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ejustice_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// Encryption key (MUST be set in production via DOC_ENC_KEY environment variable)
define('DOC_ENC_METHOD', 'AES-256-CBC');
define('DOC_ENC_KEY', getenv('DOC_ENC_KEY') ?: hash('sha256', 'CHANGE_ME_SUPER_SECRET_KEY'));

// App environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') ?: false);
