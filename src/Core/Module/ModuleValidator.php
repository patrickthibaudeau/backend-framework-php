<?php

namespace DevFramework\Core\Module;

use DevFramework\Core\Module\Exceptions\ModuleException;

/**
 * Module Validator for ensuring modules follow the required structure
 */
class ModuleValidator
{
    /**
     * Validate module structure
     */
    public static function validateModule(string $modulePath): bool
    {
        $moduleName = basename($modulePath);
        
        // Check if module directory exists
        if (!is_dir($modulePath)) {
            throw new ModuleException("Module directory does not exist: {$modulePath}");
        }

        // Check for required version.php file
        $versionFile = $modulePath . '/version.php';
        if (!file_exists($versionFile)) {
            throw new ModuleException("Module {$moduleName} missing required version.php file");
        }

        // Check for required lang directory
        $langDir = $modulePath . '/lang';
        if (!is_dir($langDir)) {
            throw new ModuleException("Module {$moduleName} missing required lang directory");
        }

        // Check for default 'en' language directory
        $defaultLangDir = $langDir . '/en';
        if (!is_dir($defaultLangDir)) {
            throw new ModuleException("Module {$moduleName} missing required default 'en' language directory");
        }

        // Validate version.php content
        self::validateVersionFile($versionFile, $moduleName);

        return true;
    }

    /**
     * Validate version.php file content
     */
    private static function validateVersionFile(string $versionFile, string $moduleName): void
    {
        // Create isolated scope for version file
        $PLUGIN = new \stdClass();
        
        // Include the version file
        include $versionFile;
        
        // Check required properties
        $requiredProperties = ['version', 'release', 'component', 'maturity'];
        foreach ($requiredProperties as $property) {
            if (!isset($PLUGIN->$property)) {
                throw new ModuleException("Module {$moduleName} version.php missing required property: {$property}");
            }
        }

        // Validate version format (should be semantic versioning)
        if (!preg_match('/^\d+\.\d+\.\d+$/', $PLUGIN->version)) {
            throw new ModuleException("Module {$moduleName} version format should follow semantic versioning (x.y.z)");
        }

        // Validate maturity values
        $validMaturity = ['MATURITY_ALPHA', 'MATURITY_BETA', 'MATURITY_RC', 'MATURITY_STABLE'];
        if (!in_array($PLUGIN->maturity, $validMaturity)) {
            throw new ModuleException("Module {$moduleName} maturity must be one of: " . implode(', ', $validMaturity));
        }
    }

    /**
     * Create module skeleton
     */
    public static function createModuleSkeleton(string $modulesPath, string $moduleName, array $moduleInfo = []): void
    {
        $modulePath = $modulesPath . '/' . $moduleName;
        
        // Create module directory
        if (!is_dir($modulePath)) {
            mkdir($modulePath, 0755, true);
        }

        // Create lang directory structure
        $langPath = $modulePath . '/lang';
        if (!is_dir($langPath)) {
            mkdir($langPath, 0755, true);
        }

        $defaultLangPath = $langPath . '/en';
        if (!is_dir($defaultLangPath)) {
            mkdir($defaultLangPath, 0755, true);
        }

        // Create version.php if it doesn't exist
        $versionFile = $modulePath . '/version.php';
        if (!file_exists($versionFile)) {
            $version = $moduleInfo['version'] ?? '1.0.0';
            $release = $moduleInfo['release'] ?? '1.0.0';
            $component = $moduleInfo['component'] ?? $moduleName;
            $maturity = $moduleInfo['maturity'] ?? 'MATURITY_STABLE';

            $versionContent = "<?php\n\n";
            $versionContent .= "// Module: {$moduleName}\n";
            $versionContent .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $versionContent .= "\$PLUGIN = new stdClass();\n";
            $versionContent .= "\$PLUGIN->version = '{$version}';\n";
            $versionContent .= "\$PLUGIN->release = '{$release}';\n";
            $versionContent .= "\$PLUGIN->component = '{$component}';\n";
            $versionContent .= "\$PLUGIN->maturity = '{$maturity}';\n";

            file_put_contents($versionFile, $versionContent);
        }

        // Create basic language file
        $defaultLangFile = $defaultLangPath . '/strings.php';
        if (!file_exists($defaultLangFile)) {
            $langContent = "<?php\n\n";
            $langContent .= "// Language strings for {$moduleName} module\n";
            $langContent .= "// Language: English (en)\n\n";
            $langContent .= "\$string = [];\n";
            $langContent .= "\$string['module_name'] = '{$moduleName}';\n";
            $langContent .= "\$string['module_description'] = 'Description for {$moduleName} module';\n";

            file_put_contents($defaultLangFile, $langContent);
        }
    }
}
