<?php
/**
 * Direct Database Connection Test
 * This bypasses the framework to test raw MySQL connectivity
 */

header('Content-Type: text/html; charset=UTF-8');

echo '<!DOCTYPE html>
<html>
<head>
    <title>Direct MySQL Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; }
        .error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; }
        .info { color: blue; background: #f0f0ff; padding: 10px; border: 1px solid blue; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Direct MySQL Connection Test</h1>';

// Load environment variables manually
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
    echo '<div class="success">✓ .env file loaded</div>';
} else {
    echo '<div class="error">❌ .env file not found at: ' . $envFile . '</div>';
}

// Show environment variables
echo '<div class="info">Environment Variables:</div>';
echo '<pre>';
echo 'DB_HOST: ' . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
echo 'DB_PORT: ' . ($_ENV['DB_PORT'] ?? 'NOT SET') . "\n";
echo 'DB_DATABASE: ' . ($_ENV['DB_DATABASE'] ?? 'NOT SET') . "\n";
echo 'DB_USERNAME: ' . ($_ENV['DB_USERNAME'] ?? 'NOT SET') . "\n";
echo 'DB_PASSWORD: ' . (isset($_ENV['DB_PASSWORD']) ? '***SET***' : 'NOT SET') . "\n";
echo '</pre>';

// Test 1: Connect to MySQL without database
echo '<h2>Test 1: Connect to MySQL (without database)</h2>';
try {
    $host = $_ENV['DB_HOST'] ?? 'mysql';
    $port = $_ENV['DB_PORT'] ?? 3306;
    $username = $_ENV['DB_USERNAME'] ?? 'devframework';
    $password = $_ENV['DB_PASSWORD'] ?? 'devframework';

    $dsn = "mysql:host={$host};port={$port}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);

    echo '<div class="success">✓ Successfully connected to MySQL server</div>';

    // Test 2: Create database
    echo '<h2>Test 2: Create database</h2>';
    $dbName = $_ENV['DB_DATABASE'] ?? 'devframework';
    $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo '<div class="success">✓ Database "' . $dbName . '" created or already exists</div>';

    // Test 3: Connect to the specific database
    echo '<h2>Test 3: Connect to specific database</h2>';
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName}";
    $pdo2 = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<div class="success">✓ Successfully connected to database "' . $dbName . '"</div>';

    // Test 4: Show databases
    echo '<h2>Test 4: List databases</h2>';
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo '<div class="info">Available databases:</div>';
    echo '<pre>' . implode("\n", $databases) . '</pre>';

    // Test 5: Show tables in our database
    echo '<h2>Test 5: Show tables in ' . $dbName . '</h2>';
    $pdo2->exec("USE `{$dbName}`");
    $stmt = $pdo2->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($tables) {
        echo '<div class="info">Tables in ' . $dbName . ':</div>';
        echo '<pre>' . implode("\n", $tables) . '</pre>';
    } else {
        echo '<div class="info">No tables found in ' . $dbName . ' (this is expected for a new database)</div>';
    }

} catch (PDOException $e) {
    echo '<div class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="error">Connection details used:</div>';
    echo '<pre>';
    echo 'Host: ' . ($host ?? 'undefined') . "\n";
    echo 'Port: ' . ($port ?? 'undefined') . "\n";
    echo 'Username: ' . ($username ?? 'undefined') . "\n";
    echo 'Password: ' . (isset($password) ? '***SET***' : 'undefined') . "\n";
    echo 'DSN: ' . ($dsn ?? 'undefined') . "\n";
    echo '</pre>';
} catch (Exception $e) {
    echo '<div class="error">❌ General error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '<p><a href="javascript:window.location.reload()">🔄 Refresh Test</a></p>';
echo '</body></html>';
?>
