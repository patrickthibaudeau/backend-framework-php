#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/helpers.php';

use DevFramework\Core\Console\ConfigCommand;

// Simple CLI entry point for configuration management
$command = new ConfigCommand();
$command->run($argv);
