<?php
/**
 * Auth Component Version Information
 */

// Define maturity constants if not already defined
if (!defined('MATURITY_STABLE')) {
    define('MATURITY_STABLE', 'stable');
}

$PLUGIN = new stdClass();
$PLUGIN->version = '2.0';
$PLUGIN->requires = '1.0';
$PLUGIN->component = 'core_auth';
$PLUGIN->maturity = MATURITY_STABLE;
$PLUGIN->release = '2.0.0';
