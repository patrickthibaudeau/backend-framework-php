<?php

namespace DevFramework\Core\Module;

/**
 * Module helper functions
 */
class ModuleHelper
{
    /**
     * Initialize the module system
     */
    public static function initialize(): void
    {
        $moduleManager = ModuleManager::getInstance();
        $moduleManager->discoverModules();
        $moduleManager->loadAllModules();
    }

    /**
     * Get a language string from a module
     */
    public static function lang(string $moduleName, string $key, array $params = [], ?string $language = null): string
    {
        $languageManager = LanguageManager::getInstance();
        return $languageManager->formatString($moduleName, $key, $params, $language);
    }

    /**
     * Check if a module is available and loaded
     */
    public static function isModuleAvailable(string $moduleName): bool
    {
        $moduleManager = ModuleManager::getInstance();
        return $moduleManager->isModuleLoaded($moduleName);
    }

    /**
     * Create a new module
     */
    public static function createModule(string $moduleName, array $moduleInfo = []): bool
    {
        try {
            $moduleManager = ModuleManager::getInstance();
            ModuleValidator::createModuleSkeleton($moduleManager->getModulesPath(), $moduleName, $moduleInfo);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get module version information
     */
    public static function getModuleInfo(string $moduleName): ?array
    {
        $moduleManager = ModuleManager::getInstance();
        return $moduleManager->getModule($moduleName);
    }

    /**
     * List all available modules
     */
    public static function listModules(): array
    {
        $moduleManager = ModuleManager::getInstance();
        return $moduleManager->getAllModules();
    }
}
