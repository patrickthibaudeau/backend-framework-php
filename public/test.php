<?php
// Simple test file to verify PHP and environment variables are working

echo "<h1>DevFramework - Docker Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

// Display some PHP configuration
echo "<h2>PHP Configuration</h2>";
echo "<ul>";
echo "<li>Memory Limit: " . ini_get('memory_limit') . "</li>";
echo "<li>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>Post Max Size: " . ini_get('post_max_size') . "</li>";
echo "<li>Max Execution Time: " . ini_get('max_execution_time') . "</li>";
echo "<li>OPcache Enabled: " . (extension_loaded('opcache') && ini_get('opcache.enable') ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// Display environment variables
echo "<h2>Environment Variables</h2>";
echo "<ul>";
echo "<li>APP_ENV: " . ($_ENV['APP_ENV'] ?? 'not set') . "</li>";
echo "<li>APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'not set') . "</li>";
echo "</ul>";

// Show loaded PHP extensions
echo "<h2>PHP Extensions</h2>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<p>" . implode(', ', $extensions) . "</p>";

phpinfo();
?>
