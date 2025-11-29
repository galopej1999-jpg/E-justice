<?php
require_once __DIR__ . '/config.php';

try {
    // Support DATABASE_URL format (from Railway, Heroku, etc.)
    $databaseUrl = getenv('DATABASE_URL');
    
    if ($databaseUrl) {
        // Parse DATABASE_URL format: mysql://user:password@host:port/dbname
        $url = parse_url($databaseUrl);
        $host = $url['host'] ?? 'localhost';
        $port = $url['port'] ?? 3306;
        $user = $url['user'] ?? 'root';
        $pass = $url['pass'] ?? '';
        $dbname = ltrim($url['path'] ?? '', '/');
        
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    } else {
        // Use config.php fallback (for local development)
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $user = DB_USER;
        $pass = DB_PASS;
    }
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
