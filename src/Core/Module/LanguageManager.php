<?php

namespace DevFramework\Core\Module;

use DevFramework\Core\Module\Exceptions\ModuleException;

/**
 * Language Manager for module internationalization
 */
class LanguageManager
{
    private static ?LanguageManager $instance = null;
    private array $loadedStrings = [];
    private string $currentLanguage = 'en';
    private ModuleManager $moduleManager;

    private function __construct()
    {
        $this->moduleManager = ModuleManager::getInstance();
    }

    public static function getInstance(): LanguageManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set current language
     */
    public function setLanguage(string $language): void
    {
        $this->currentLanguage = $language;
        // Clear loaded strings to force reload with new language
        $this->loadedStrings = [];
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * Load language strings for a module
     */
    public function loadModuleStrings(string $moduleName, ?string $language = null): void
    {
        $language = $language ?? $this->currentLanguage;
        $cacheKey = "{$moduleName}:{$language}";

        if (isset($this->loadedStrings[$cacheKey])) {
            return;
        }

        $languagePath = $this->moduleManager->getModuleLanguagePath($moduleName, $language);

        // Fallback to default language if requested language not found
        if (!$languagePath && $language !== 'en') {
            $languagePath = $this->moduleManager->getModuleLanguagePath($moduleName, 'en');
        }

        if (!$languagePath) {
            throw new ModuleException("Language files not found for module {$moduleName}");
        }

        $this->loadedStrings[$cacheKey] = [];

        // Load all PHP files in the language directory
        $languageFiles = glob($languagePath . '/*.php');
        foreach ($languageFiles as $file) {
            $string = [];
            include $file;

            if (isset($string) && is_array($string)) {
                $this->loadedStrings[$cacheKey] = array_merge($this->loadedStrings[$cacheKey], $string);
            }
        }
    }

    /**
     * Get a language string
     */
    public function getString(string $moduleName, string $key, ?string $language = null): ?string
    {
        $language = $language ?? $this->currentLanguage;
        $cacheKey = "{$moduleName}:{$language}";

        // Load strings if not already loaded
        if (!isset($this->loadedStrings[$cacheKey])) {
            $this->loadModuleStrings($moduleName, $language);
        }

        return $this->loadedStrings[$cacheKey][$key] ?? null;
    }

    /**
     * Get all strings for a module
     */
    public function getModuleStrings(string $moduleName, ?string $language = null): array
    {
        $language = $language ?? $this->currentLanguage;
        $cacheKey = "{$moduleName}:{$language}";

        // Load strings if not already loaded
        if (!isset($this->loadedStrings[$cacheKey])) {
            $this->loadModuleStrings($moduleName, $language);
        }

        return $this->loadedStrings[$cacheKey] ?? [];
    }

    /**
     * Check if a string exists
     */
    public function hasString(string $moduleName, string $key, ?string $language = null): bool
    {
        return $this->getString($moduleName, $key, $language) !== null;
    }

    /**
     * Get available languages for a module
     */
    public function getAvailableLanguages(string $moduleName): array
    {
        $module = $this->moduleManager->getModule($moduleName);
        if (!$module) {
            return [];
        }

        $langPath = $module['path'] . '/lang';
        if (!is_dir($langPath)) {
            return [];
        }

        $languages = array_filter(glob($langPath . '/*'), 'is_dir');
        return array_map('basename', $languages);
    }

    /**
     * Format a string with parameters
     */
    public function formatString(string $moduleName, string $key, array $params = [], ?string $language = null): string
    {
        $string = $this->getString($moduleName, $key, $language);

        if ($string === null) {
            return $key; // Return key as fallback
        }

        // Replace placeholders like {param}
        foreach ($params as $paramKey => $paramValue) {
            $string = str_replace('{' . $paramKey . '}', $paramValue, $string);
        }

        return $string;
    }
}
