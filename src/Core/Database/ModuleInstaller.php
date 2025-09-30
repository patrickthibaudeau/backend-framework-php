<?php

namespace DevFramework\Core\Database;

use DevFramework\Core\Module\ModuleManager;

/**
 * Module database installer
 * Handles installing and upgrading database schemas for modules
 */
class ModuleInstaller
{
    private SchemaBuilder $schemaBuilder;
    private Database $database;

    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->schemaBuilder = new SchemaBuilder($this->database);
    }

    /**
     * Install database tables for a module
     */
    public function installModule(string $moduleName): bool
    {
        try {
            error_log("ModuleInstaller: Starting installation for module '{$moduleName}'");

            // Check if module tables are already installed
            if ($this->isModuleInstalled($moduleName)) {
                error_log("ModuleInstaller: Module '{$moduleName}' already installed");
                return true; // Already installed
            }

            $schemaPath = $this->getModuleSchemaPath($moduleName);
            error_log("ModuleInstaller: Schema path for '{$moduleName}': {$schemaPath}");

            if (!file_exists($schemaPath)) {
                error_log("ModuleInstaller: No database schema file found for '{$moduleName}' at {$schemaPath}");
                // No database schema file found - that's okay, not all modules need databases
                // Still set version to mark module as "installed"
                $version = $this->getModuleVersion($moduleName);
                if ($version) {
                    $this->database->set_plugin_version($moduleName, $version);
                    error_log("ModuleInstaller: Set version '{$version}' for module '{$moduleName}' (no database needed)");
                }
                return true;
            }

            $schemas = $this->loadSchemaFile($schemaPath);
            error_log("ModuleInstaller: Loaded " . count($schemas) . " table schemas for '{$moduleName}'");

            if (empty($schemas)) {
                error_log("ModuleInstaller: No table schemas found in {$schemaPath}");
                // Set version even if no schemas
                $version = $this->getModuleVersion($moduleName);
                if ($version) {
                    $this->database->set_plugin_version($moduleName, $version);
                }
                return true;
            }

            foreach ($schemas as $tableName => $schema) {
                error_log("ModuleInstaller: Processing table '{$tableName}' for module '{$moduleName}'");

                if (!$this->schemaBuilder->tableExists($tableName)) {
                    error_log("ModuleInstaller: Creating table '{$tableName}'");
                    $this->schemaBuilder->createTable($tableName, $schema);
                    error_log("ModuleInstaller: Created table '{$tableName}' for module '{$moduleName}'");
                } else {
                    error_log("ModuleInstaller: Table '{$tableName}' already exists");
                }
            }

            // Set module version in config_plugins after successful installation
            $version = $this->getModuleVersion($moduleName);
            if ($version) {
                $this->database->set_plugin_version($moduleName, $version);
                error_log("ModuleInstaller: Set version '{$version}' for module '{$moduleName}'");
            } else {
                error_log("ModuleInstaller: Warning - No version found for module '{$moduleName}'");
            }

            error_log("ModuleInstaller: Successfully completed installation for module '{$moduleName}'");
            return true;
        } catch (\Exception $e) {
            error_log("ModuleInstaller: Failed to install module '{$moduleName}': " . $e->getMessage());
            error_log("ModuleInstaller: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Check if a module's database is already installed
     */
    private function isModuleInstalled(string $moduleName): bool
    {
        return $this->database->get_plugin_version($moduleName) !== null;
    }

    /**
     * Get the path to a module's database schema file
     */
    private function getModuleSchemaPath(string $moduleName): string
    {
        $moduleManager = ModuleManager::getInstance();
        $modulePath = $moduleManager->getModulePath($moduleName);
        return $modulePath . '/db/install.php';
    }

    /**
     * Load schema definitions from a module's install.php file
     */
    private function loadSchemaFile(string $schemaPath): array
    {
        if (!file_exists($schemaPath)) {
            return [];
        }

        $schemas = include $schemaPath;
        return is_array($schemas) ? $schemas : [];
    }

    /**
     * Get module version from its version.php file
     */
    private function getModuleVersion(string $moduleName): ?string
    {
        $moduleManager = ModuleManager::getInstance();
        $moduleInfo = $moduleManager->getModuleInfo($moduleName);
        return $moduleInfo['version'] ?? null;
    }

    /**
     * Install all discovered modules
     */
    public function installAllModules(): array
    {
        $results = [];
        $moduleManager = ModuleManager::getInstance();
        $modules = $moduleManager->getAllModules();

        foreach ($modules as $moduleName => $moduleInfo) {
            try {
                if ($this->installModule($moduleName)) {
                    $results[$moduleName] = 'Installed successfully';
                } else {
                    $results[$moduleName] = 'Installation failed';
                }
            } catch (\Exception $e) {
                $results[$moduleName] = 'Error: ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Upgrade a module from one version to another
     */
    public function upgradeModule(string $moduleName, string $fromVersion, string $toVersion): bool
    {
        try {
            $upgradePath = $this->getModuleUpgradePath($moduleName);

            if (!file_exists($upgradePath)) {
                // No upgrade file - just update the version
                $this->database->set_plugin_version($moduleName, $toVersion);
                return true;
            }

            // Load and execute upgrade scripts
            $upgradeScripts = include $upgradePath;
            if (is_array($upgradeScripts)) {
                foreach ($upgradeScripts as $version => $scripts) {
                    if (version_compare($version, $fromVersion, '>') &&
                        version_compare($version, $toVersion, '<=')) {
                        $this->executeUpgradeScripts($scripts);
                    }
                }
            }

            // Update version
            $this->database->set_plugin_version($moduleName, $toVersion);
            return true;
        } catch (\Exception $e) {
            error_log("ModuleInstaller: Failed to upgrade module '{$moduleName}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the path to a module's upgrade file
     */
    private function getModuleUpgradePath(string $moduleName): string
    {
        $moduleManager = ModuleManager::getInstance();
        $modulePath = $moduleManager->getModulePath($moduleName);
        return $modulePath . '/db/upgrade.php';
    }

    /**
     * Execute upgrade scripts
     */
    private function executeUpgradeScripts(array $scripts): void
    {
        foreach ($scripts as $script) {
            if (is_string($script)) {
                // Raw SQL
                $this->database->execute($script);
            } elseif (is_callable($script)) {
                // Callable function
                $script($this->database, $this->schemaBuilder);
            }
        }
    }

    /**
     * Uninstall a module's database components
     */
    public function uninstallModule(string $moduleName): bool
    {
        try {
            // Remove all tables for this module
            $schemaPath = $this->getModuleSchemaPath($moduleName);
            if (file_exists($schemaPath)) {
                $schemas = $this->loadSchemaFile($schemaPath);
                foreach (array_keys($schemas) as $tableName) {
                    $this->schemaBuilder->dropTable($tableName);
                }
            }

            // Remove version record
            $this->database->unset_plugin_configs($moduleName);
            return true;
        } catch (\Exception $e) {
            error_log("ModuleInstaller: Failed to uninstall module '{$moduleName}': " . $e->getMessage());
            return false;
        }
    }
}
