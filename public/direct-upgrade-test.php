<?php
/**
 * Direct Upgrade Test Script
 * This bypasses the framework initialization to test upgrade logic directly
 */

// Load only what we need
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables manually
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "<h1>Direct Upgrade Test</h1>";
echo "<pre>";

try {
    echo "1. Testing database connection...\n";

    $host = $_ENV['DB_HOST'] ?? 'mysql';
    $port = $_ENV['DB_PORT'] ?? 3306;
    $database = $_ENV['DB_DATABASE'] ?? 'devframework';
    $username = $_ENV['DB_USERNAME'] ?? 'devframework';
    $password = $_ENV['DB_PASSWORD'] ?? 'devframework';

    $dsn = "mysql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✓ Database connection successful\n\n";

    echo "2. Checking config_plugins table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'dev_config_plugins'");
    if ($stmt->fetchColumn()) {
        echo "✓ config_plugins table exists\n";

        // Show current module versions
        $stmt = $pdo->query("SELECT plugin, name, value FROM dev_config_plugins WHERE name = 'version'");
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Current module versions in database:\n";
        foreach ($versions as $version) {
            echo "  - {$version['plugin']}: {$version['value']}\n";
        }
    } else {
        echo "❌ config_plugins table does not exist\n";
    }

    echo "\n3. Checking module files...\n";

    // Load module constants first before including any version files
    require_once __DIR__ . '/../src/Core/Module/constants.php';

    $modulesDir = __DIR__ . '/../src/modules';
    if (is_dir($modulesDir)) {
        $modules = array_filter(glob($modulesDir . '/*'), 'is_dir');
        foreach ($modules as $moduleDir) {
            $moduleName = basename($moduleDir);
            $versionFile = $moduleDir . '/version.php';

            if (file_exists($versionFile)) {
                // Load version safely
                $PLUGIN = new stdClass();
                include $versionFile;
                $fileVersion = $PLUGIN->version ?? 'unknown';

                echo "  - {$moduleName}: version.php = {$fileVersion}\n";

                // Check if this module needs upgrade
                if (!empty($versions)) {
                    $dbVersion = null;
                    foreach ($versions as $v) {
                        if ($v['plugin'] === $moduleName) {
                            $dbVersion = $v['value'];
                            break;
                        }
                    }

                    if ($dbVersion) {
                        $comparison = version_compare($fileVersion, $dbVersion);
                        if ($comparison > 0) {
                            echo "    → NEEDS UPGRADE: {$dbVersion} → {$fileVersion}\n";
                        } else if ($comparison === 0) {
                            echo "    → UP TO DATE\n";
                        } else {
                            echo "    → DOWNGRADE?: {$dbVersion} → {$fileVersion}\n";
                        }
                    } else {
                        echo "    → NOT IN DATABASE (needs install)\n";
                    }
                }
            } else {
                echo "  - {$moduleName}: NO version.php file\n";
            }
        }
    }

    echo "\n4. Testing direct version update...\n";

    // Test updating a version directly
    $testModule = 'Test';
    $newVersion = '1.0.' . time(); // Use timestamp to ensure it's newer

    echo "Attempting to set {$testModule} version to {$newVersion}...\n";

    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM dev_config_plugins WHERE plugin = ? AND name = 'version'");
    $stmt->execute([$testModule]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE dev_config_plugins SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
        $result = $stmt->execute([$newVersion, time(), $testModule]);
        echo $result ? "✓ Updated existing version record\n" : "❌ Failed to update version record\n";
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO dev_config_plugins (plugin, name, value, timecreated, timemodified) VALUES (?, 'version', ?, ?, ?)");
        $currentTime = time();
        $result = $stmt->execute([$testModule, $newVersion, $currentTime, $currentTime]);
        echo $result ? "✓ Created new version record\n" : "❌ Failed to create version record\n";
    }

    // Verify the update
    $stmt = $pdo->prepare("SELECT value FROM dev_config_plugins WHERE plugin = ? AND name = 'version'");
    $stmt->execute([$testModule]);
    $updatedVersion = $stmt->fetchColumn();
    echo "Verified version in database: {$updatedVersion}\n";

    echo "\n✓ Direct upgrade test completed successfully!\n";
    echo "The database operations work fine. The issue is likely in the framework initialization logic.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "</pre>";
?>
