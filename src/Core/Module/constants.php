<?php

/**
 * Module System Constants
 * This file defines constants used by the module system
 */

// Module maturity levels
if (!defined('MATURITY_ALPHA')) {
    define('MATURITY_ALPHA', 'MATURITY_ALPHA');
}
if (!defined('MATURITY_BETA')) {
    define('MATURITY_BETA', 'MATURITY_BETA');
}
if (!defined('MATURITY_RC')) {
    define('MATURITY_RC', 'MATURITY_RC');
}
if (!defined('MATURITY_STABLE')) {
    define('MATURITY_STABLE', 'MATURITY_STABLE');
}

// Module system constants
if (!defined('MODULE_VERSION_REQUIRED')) {
    define('MODULE_VERSION_REQUIRED', serialize(['version', 'release', 'component', 'maturity']));
}
