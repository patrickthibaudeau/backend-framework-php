<?php

namespace DevFramework\Core\Module;

use DevFramework\Core\Module\Exceptions\ModuleException;

/**
 * Module Manager for handling framework modules
 */
class ModuleManager
{
    private static ?ModuleManager $instance = null;
    private array $modules = [];
    private array $loadedModules = [];
    private string $modulesPath;

    private function __construct()
    {
        $this->modulesPath = dirname(__DIR__, 2) . '/modules';
    }

    public static function getInstance(): ModuleManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Discover all modules in the modules directory
     */
    public function discoverModules(): void
    {
        if (!is_dir($this->modulesPath)) {
            mkdir($this->modulesPath, 0755, true);
        }

        $directories = array_filter(glob($this->modulesPath . '/*'), 'is_dir');

        foreach ($directories as $moduleDir) {
            $moduleName = basename($moduleDir);
            $versionFile = $moduleDir . '/version.php';

            if (file_exists($versionFile)) {
                $this->loadModuleInfo($moduleName, $versionFile);
            }
        }
    }

    /**
     * Load module information from version.php
     */
    private function loadModuleInfo(string $moduleName, string $versionFile): void
    {
        // Create a clean scope for loading the version file
        $PLUGIN = new \stdClass();

        // Include the version file
        include $versionFile;

        // Validate required properties
        $requiredProperties = ['version', 'release', 'component', 'maturity'];
        foreach ($requiredProperties as $property) {
            if (!isset($PLUGIN->$property)) {
                throw new ModuleException("Module {$moduleName} version.php missing required property: {$property}");
            }
        }

        $this->modules[$moduleName] = [
            'name' => $moduleName,
            'version' => $PLUGIN->version,
            'release' => $PLUGIN->release,
            'component' => $PLUGIN->component,
            'maturity' => $PLUGIN->maturity,
            'path' => dirname($versionFile),
            'loaded' => false
        ];
    }

    /**
     * Load a specific module
     */
    public function loadModule(string $moduleName): bool
    {
        if (!isset($this->modules[$moduleName])) {
            throw new ModuleException("Module {$moduleName} not found");
        }

        if ($this->isModuleLoaded($moduleName)) {
            return true;
        }

        $module = $this->modules[$moduleName];

        // Load module autoloader if exists
        $autoloadFile = $module['path'] . '/autoload.php';
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
        }

        // Mark as loaded
        $this->modules[$moduleName]['loaded'] = true;
        $this->loadedModules[] = $moduleName;

        return true;
    }

    /**
     * Load all discovered modules
     */
    public function loadAllModules(): void
    {
        foreach (array_keys($this->modules) as $moduleName) {
            $this->loadModule($moduleName);
        }
    }

    /**
     * Check if a module is loaded
     */
    public function isModuleLoaded(string $moduleName): bool
    {
        return isset($this->modules[$moduleName]) && $this->modules[$moduleName]['loaded'];
    }

    /**
     * Get module information
     */
    public function getModule(string $moduleName): ?array
    {
        return $this->modules[$moduleName] ?? null;
    }

    /**
     * Get all modules
     */
    public function getAllModules(): array
    {
        return $this->modules;
    }

    /**
     * Get loaded modules
     */
    public function getLoadedModules(): array
    {
        return array_filter($this->modules, fn($module) => $module['loaded']);
    }

    /**
     * Get module language path
     */
    public function getModuleLanguagePath(string $moduleName, string $language = 'en'): ?string
    {
        if (!isset($this->modules[$moduleName])) {
            return null;
        }

        $langPath = $this->modules[$moduleName]['path'] . '/lang/' . $language;
        return is_dir($langPath) ? $langPath : null;
    }

    /**
     * Get modules path
     */
    public function getModulesPath(): string
    {
        return $this->modulesPath;
    }
}
