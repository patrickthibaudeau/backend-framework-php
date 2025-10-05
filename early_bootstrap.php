<?php
/**
 * Early bootstrap to suppress vendor (Mustache) deprecation warnings that occur at class load/parse time
 * BEFORE composer autoloader brings those classes into scope.
 *
 * This targets PHP 8.4 deprecations like implicit nullable parameters in vendor libraries
 * we do not control. Runtime Mustache deprecations are already suppressed later in helpers.
 *
 * To disable suppression set environment variable MUSTACHE_SUPPRESS_DEPRECATIONS=false
 */
declare(strict_types=1);

if (!function_exists('__df_install_early_vendor_deprecation_handler')) {
    function __df_install_early_vendor_deprecation_handler(): void
    {
        static $installed = false;
        if ($installed) { return; }

        $env = getenv('MUSTACHE_SUPPRESS_DEPRECATIONS');
        $suppress = true; // default ON
        if ($env !== false) {
            $norm = strtolower(trim($env));
            if (in_array($norm, ['0','false','off','no'], true)) {
                $suppress = false;
            }
        }
        if (!$suppress) { return; }

        set_error_handler(function($errno, $errstr, $errfile = null, $errline = null) {
            if (($errno & E_DEPRECATED) && $errfile && str_contains($errfile, '/mustache/mustache/')) {
                // Swallow Mustache deprecations produced during file load / class parse
                return true;
            }
            return false; // allow normal handling for everything else
        });
        $installed = true;
    }
}

__df_install_early_vendor_deprecation_handler();

