<?php

namespace DevFramework\Core\Console;

use DevFramework\Core\Module\ModuleManager;
use DevFramework\Core\Module\ModuleValidator;
use DevFramework\Core\Module\LanguageManager;
use DevFramework\Core\Module\Exceptions\ModuleException;

/**
 * Console command for module management
 */
class ModuleCommand
{
    private ModuleManager $moduleManager;
    private LanguageManager $languageManager;

    public function __construct()
    {
        $this->moduleManager = ModuleManager::getInstance();
        $this->languageManager = LanguageManager::getInstance();
    }

    /**
     * Handle module commands
     */
    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->showHelp();
            return;
        }

        $command = array_shift($args);

        switch ($command) {
            case 'list':
                $this->listModules();
                break;
            case 'create':
                $this->createModule($args);
                break;
            case 'validate':
                $this->validateModule($args);
                break;
            case 'info':
                $this->showModuleInfo($args);
                break;
            case 'languages':
                $this->showLanguages($args);
                break;
            default:
                echo "Unknown command: {$command}\n";
                $this->showHelp();
        }
    }

    /**
     * Show help information
     */
    private function showHelp(): void
    {
        echo "Module Management Commands:\n";
        echo "  list                    - List all modules\n";
        echo "  create <name> [options] - Create a new module\n";
        echo "  validate <name>         - Validate module structure\n";
        echo "  info <name>             - Show module information\n";
        echo "  languages <name>        - Show available languages for module\n";
        echo "\nCreate options:\n";
        echo "  --version=<version>     - Set module version (default: 1.0.0)\n";
        echo "  --maturity=<maturity>   - Set maturity level (ALPHA, BETA, RC, STABLE)\n";
        echo "  --component=<name>      - Set component name (default: module name)\n";
        echo "\nExample:\n";
        echo "  php console.php module create Blog --version=1.2.0 --maturity=MATURITY_BETA\n";
    }

    /**
     * List all modules
     */
    private function listModules(): void
    {
        $this->moduleManager->discoverModules();
        $modules = $this->moduleManager->getAllModules();

        if (empty($modules)) {
            echo "No modules found.\n";
            return;
        }

        echo "Available Modules:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-20s %-10s %-10s %-15s %-10s\n", "Name", "Version", "Release", "Component", "Status");
        echo str_repeat("-", 80) . "\n";

        foreach ($modules as $module) {
            $status = $module['loaded'] ? 'Loaded' : 'Available';
            printf("%-20s %-10s %-10s %-15s %-10s\n", 
                $module['name'], 
                $module['version'], 
                $module['release'], 
                $module['component'], 
                $status
            );
        }
    }

    /**
     * Create a new module
     */
    private function createModule(array $args): void
    {
        if (empty($args)) {
            echo "Error: Module name is required.\n";
            echo "Usage: module create <name> [options]\n";
            return;
        }

        $moduleName = array_shift($args);
        $options = $this->parseOptions($args);

        $moduleInfo = [
            'version' => $options['version'] ?? '1.0.0',
            'release' => $options['version'] ?? '1.0.0',
            'component' => $options['component'] ?? $moduleName,
            'maturity' => $options['maturity'] ?? 'MATURITY_STABLE'
        ];

        try {
            ModuleValidator::createModuleSkeleton(
                $this->moduleManager->getModulesPath(), 
                $moduleName, 
                $moduleInfo
            );
            echo "Module '{$moduleName}' created successfully.\n";
            echo "Location: " . $this->moduleManager->getModulesPath() . "/{$moduleName}\n";
        } catch (ModuleException $e) {
            echo "Error creating module: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Validate module structure
     */
    private function validateModule(array $args): void
    {
        if (empty($args)) {
            echo "Error: Module name is required.\n";
            echo "Usage: module validate <name>\n";
            return;
        }

        $moduleName = $args[0];
        $modulePath = $this->moduleManager->getModulesPath() . '/' . $moduleName;

        try {
            if (ModuleValidator::validateModule($modulePath)) {
                echo "Module '{$moduleName}' is valid.\n";
            }
        } catch (ModuleException $e) {
            echo "Validation failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Show module information
     */
    private function showModuleInfo(array $args): void
    {
        if (empty($args)) {
            echo "Error: Module name is required.\n";
            echo "Usage: module info <name>\n";
            return;
        }

        $moduleName = $args[0];
        $this->moduleManager->discoverModules();
        $module = $this->moduleManager->getModule($moduleName);

        if (!$module) {
            echo "Module '{$moduleName}' not found.\n";
            return;
        }

        echo "Module Information:\n";
        echo str_repeat("-", 40) . "\n";
        echo "Name:      {$module['name']}\n";
        echo "Version:   {$module['version']}\n";
        echo "Release:   {$module['release']}\n";
        echo "Component: {$module['component']}\n";
        echo "Maturity:  {$module['maturity']}\n";
        echo "Path:      {$module['path']}\n";
        echo "Status:    " . ($module['loaded'] ? 'Loaded' : 'Available') . "\n";
    }

    /**
     * Show available languages for a module
     */
    private function showLanguages(array $args): void
    {
        if (empty($args)) {
            echo "Error: Module name is required.\n";
            echo "Usage: module languages <name>\n";
            return;
        }

        $moduleName = $args[0];
        $this->moduleManager->discoverModules();
        
        if (!$this->moduleManager->getModule($moduleName)) {
            echo "Module '{$moduleName}' not found.\n";
            return;
        }

        $languages = $this->languageManager->getAvailableLanguages($moduleName);

        if (empty($languages)) {
            echo "No languages found for module '{$moduleName}'.\n";
            return;
        }

        echo "Available languages for '{$moduleName}':\n";
        foreach ($languages as $language) {
            echo "  - {$language}\n";
        }
    }

    /**
     * Parse command line options
     */
    private function parseOptions(array $args): array
    {
        $options = [];
        
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                if (count($parts) === 2) {
                    $options[$parts[0]] = $parts[1];
                }
            }
        }

        return $options;
    }
}
