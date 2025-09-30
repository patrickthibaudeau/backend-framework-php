<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/helpers.php';

use DevFramework\Core\Console\ConfigCommand;
use DevFramework\Core\Console\ModuleCommand;

// Simple console router
if ($argc < 2) {
    echo "Usage: php console.php <command> [arguments]\n";
    echo "Available commands:\n";
    echo "  config  - Configuration management\n";
    echo "  module  - Module management\n";
    exit(1);
}

$command = $argv[1];
$args = array_slice($argv, 2);

switch ($command) {
    case 'config':
        $configCommand = new ConfigCommand();
        $configCommand->handle($args);
        break;

    case 'module':
        $moduleCommand = new ModuleCommand();
        $moduleCommand->handle($args);
        break;

    default:
        echo "Unknown command: {$command}\n";
        exit(1);
}
