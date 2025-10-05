<?php
namespace DevFramework\Core\Output;

use Exception;
use DevFramework\Core\Module\LanguageManager; // Added for language strings
use DevFramework\Core\Theme\NavigationManager; // NEW for automatic nav injection

/**
 * Output rendering manager using Mustache templates.
 *
 * Usage example:
 *   global $OUTPUT;
 *   echo $OUTPUT->renderFromTemplate('auth_method', ['method' => 'Standard Login']);
 */
class Output
{
    protected static ?Output $instance = null;

    /** @var object|null Underlying Mustache engine instance */
    protected $engine = null;

    /** Additional template search roots keyed by type */
    protected array $extraRoots = [];

    /** @var string|null */
    protected ?string $cacheDir = null;
    protected bool $cacheEnabled = true;

    /** @var bool Whether to suppress known Mustache PHP 8.4 deprecation warnings */
    protected bool $suppressMustacheDeprecations = true;

    protected array $themeLangCache = []; // cache for theme language strings

    private function __construct()
    {
        $this->initializeCacheDir();
        // Allow environment override to disable suppression (set MUSTACHE_SUPPRESS_DEPRECATIONS=false)
        $envFlag = getenv('MUSTACHE_SUPPRESS_DEPRECATIONS');
        if ($envFlag !== false) {
            $normalized = strtolower(trim($envFlag));
            if (in_array($normalized, ['0','false','off','no'], true)) {
                $this->suppressMustacheDeprecations = false;
            }
        }
        // Auto-disable cache if env MUSTACHE_DISABLE_CACHE indicates truthy
        $disableCacheFlag = getenv('MUSTACHE_DISABLE_CACHE');
        if ($disableCacheFlag !== false) {
            $normalized = strtolower(trim($disableCacheFlag));
            if (in_array($normalized, ['1','true','yes','on'], true)) {
                $this->cacheEnabled = false; // prevent initial build with cache
            }
        }
        if (class_exists('Mustache_Engine')) {
            $this->buildEngine();
        }
    }

    /** Initialize (or attempt to) the default cache directory */
    protected function initializeCacheDir(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $default = $projectRoot . '/storage/cache/mustache';
        if (!is_dir($default)) {
            @mkdir($default, 0775, true);
        }
        if (is_dir($default) && is_writable($default)) {
            $this->cacheDir = $default;
        } else {
            // Fallback: disable cache if directory not writable
            $this->cacheDir = null;
            $this->cacheEnabled = false;
        }
    }

    /** Rebuild Mustache engine with current cache settings */
    protected function buildEngine(): void
    {
        if (!class_exists('Mustache_Engine')) {
            // Dependency not yet installed / autoloaded
            throw new Exception('Mustache_Engine class not found. Ensure composer dependencies are installed (composer install).');
        }
        $projectRoot = dirname(__DIR__, 3);
        $options = [
            'escape' => function ($value) {
                return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        ];
        if ($this->cacheEnabled && $this->cacheDir) {
            $options['cache'] = $this->cacheDir;
            $options['cache_file_mode'] = 0644;
        }
        // Provide partials loader so {{> header}} / {{> footer}} in theme body file resolve
        $themePartialsDir = $projectRoot . '/src/Core/Theme/default/templates';
        if (is_dir($themePartialsDir)) {
            if (class_exists('Mustache_Loader_FilesystemLoader')) {
                $options['partials_loader'] = new \Mustache_Loader_FilesystemLoader($themePartialsDir, [
                    'extension' => '.mustache'
                ]);
            }
        }
        $this->withSuppressedMustacheDeprecations(function () use ($options) {
            $this->engine = new \Mustache_Engine($options);
        });
    }

    /** Enable caching (creates directory if needed) */
    public function enableCache(): void
    {
        if (!$this->cacheDir) {
            $this->initializeCacheDir();
        }
        $this->cacheEnabled = (bool)$this->cacheDir; // Only if dir is valid
        if (class_exists('Mustache_Engine')) {
            $this->buildEngine();
        }
    }

    /** Disable caching */
    public function disableCache(): void
    {
        $this->cacheEnabled = false;
        if (class_exists('Mustache_Engine')) {
            $this->buildEngine();
        }
    }

    /** Set a custom cache directory */
    public function setCacheDir(string $dir): void
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new Exception("Cache directory '{$dir}' is not writable");
        }
        $this->cacheDir = $dir;
        if ($this->cacheEnabled) {
            $this->buildEngine();
        }
    }

    /** Get current cache directory (null if disabled) */
    public function getCacheDir(): ?string
    {
        return ($this->cacheEnabled) ? $this->cacheDir : null;
    }

    /** Clear compiled template cache */
    public function clearCache(): int
    {
        if (!$this->cacheDir || !is_dir($this->cacheDir)) {
            return 0;
        }
        $removed = 0;
        foreach (glob($this->cacheDir . '/*.php') as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    /** Render template identified by component_template naming convention. */
    public function renderFromTemplate(string $templateName, array|object $data = []): string
    {
        if (!str_contains($templateName, '_')) {
            throw new Exception("Invalid template name '{$templateName}'. Expected component_template format.");
        }
        [$componentSlug, $templateSlug] = explode('_', $templateName, 2);
        if ($componentSlug === '' || $templateSlug === '') {
            throw new Exception("Invalid template name '{$templateName}'.");
        }

        if (!$this->engine) {
            $this->buildEngine();
        }

        $dataArr = (array)$data;
        if (!array_key_exists('dirroot', $dataArr)) {
            $dataArr['dirroot'] = defined('DIRROOT') ? DIRROOT : dirname(__DIR__, 3);
        }

        // Inject language string lambda helper if not already provided
        if (!isset($dataArr['str']) || !is_callable($dataArr['str'])) {
            $dataArr['str'] = function ($text, $helper) {
                // First render nested Mustache tags inside the section so dynamic keys like {{label_key}} resolve
                if (method_exists($helper, 'render')) {
                    try { $text = $helper->render($text); } catch (\Throwable $e) { /* ignore */ }
                }
                $raw = trim($text);
                // Collapse internal whitespace/newlines
                $raw = preg_replace('/\s+/', ' ', $raw);
                // Expected format: key, component[, param=value, param2=value]
                $parts = array_map('trim', explode(',', $raw));
                $key = $parts[0] ?? '';
                $component = $parts[1] ?? '';
                $params = [];
                if (count($parts) > 2) {
                    for ($i = 2; $i < count($parts); $i++) {
                        if (str_contains($parts[$i], '=')) {
                            [$pK, $pV] = array_map('trim', explode('=', $parts[$i], 2));
                            if ($pK !== '') { $params[$pK] = $pV; }
                        }
                    }
                }
                if ($key === '') { return ''; }

                $language = 'en';
                try { $lm = LanguageManager::getInstance(); $language = $lm->getCurrentLanguage(); } catch (\Throwable $e) { /* ignore */ }
                $value = null;

                if ($component !== '' && str_starts_with($component, 'theme_')) {
                    $value = $this->getThemeLangString($component, $key, $language, $params);
                } elseif ($component !== '') {
                    try {
                        $lm = LanguageManager::getInstance();
                        $value = $lm->formatString($component, $key, $params, $language);
                        if ($value === $key) { $value = null; }
                    } catch (\Throwable $e) { $value = null; }
                }
                if ($value === null) {
                    $value = $this->getThemeLangString('theme_default', $key, $language, $params);
                }
                return $value ?? $key;
            };
        }

        $path = $this->resolveTemplatePath($componentSlug, $templateSlug);
        if (!$path || !is_file($path)) {
            throw new Exception("Template '{$templateName}' not found (searched for '{$path}').");
        }

        $source = file_get_contents($path);
        return $this->withSuppressedMustacheDeprecations(function () use ($source, $dataArr) {
            return $this->engine->render($source, $dataArr);
        });
    }

    protected function getThemeLangString(string $themeComponent, string $key, string $language, array $params = []): ?string
    {
        // themeComponent format: theme_default
        $themeName = substr($themeComponent, strlen('theme_')) ?: 'default';
        $cacheKey = $themeName . ':' . $language;
        if (!isset($this->themeLangCache[$cacheKey])) {
            $this->themeLangCache[$cacheKey] = [];
            $root = defined('DIRROOT') ? DIRROOT : dirname(__DIR__, 3);
            $langFile = $root . '/src/Core/Theme/' . $themeName . '/lang/' . $language . '/strings.php';
            if (!is_file($langFile) && $language !== 'en') {
                // fallback to en
                $langFile = $root . '/src/Core/Theme/' . $themeName . '/lang/en/strings.php';
            }
            if (is_file($langFile)) {
                $string = [];
                try { include $langFile; } catch (\Throwable $e) { /* ignore */ }
                if (isset($string) && is_array($string)) {
                    $this->themeLangCache[$cacheKey] = $string;
                }
            }
        }
        $value = $this->themeLangCache[$cacheKey][$key] ?? null;
        if ($value !== null && $params) {
            foreach ($params as $pK => $pV) {
                $value = str_replace('{' . $pK . '}', $pV, $value);
            }
        }
        return $value;
    }

    /** Determine full file path for a component + slug */
    protected function resolveTemplatePath(string $componentSlug, string $templateSlug): ?string
    {
        // Project root (three levels up from /src/Core/Output)
        $projectRoot = dirname(__DIR__, 3);

        $candidateNames = [];
        $pascal = $this->toPascalCase($componentSlug);

        // Core component (PascalCase) under src/Core
        $candidateNames[] = $projectRoot . '/src/Core/' . $pascal . '/templates/' . $templateSlug . '.mustache';
        // Core lowercase fallback
        $candidateNames[] = $projectRoot . '/src/Core/' . strtolower($componentSlug) . '/templates/' . $templateSlug . '.mustache';
        // NEW: Theme default variant path support (e.g., /src/Core/Theme/default/templates)
        $candidateNames[] = $projectRoot . '/src/Core/' . $pascal . '/default/templates/' . $templateSlug . '.mustache';
        // Module component (PascalCase) under root /modules (corrected path)
        $candidateNames[] = $projectRoot . '/modules/' . $pascal . '/templates/' . $templateSlug . '.mustache';
        // Module lowercase fallback
        $candidateNames[] = $projectRoot . '/modules/' . strtolower($componentSlug) . '/templates/' . $templateSlug . '.mustache';
        // ADDITION: Allow placing arbitrary component templates directly in the active theme default template directory
        $candidateNames[] = $projectRoot . '/src/Core/Theme/default/templates/' . $componentSlug . '_' . $templateSlug . '.mustache';

        // Registered extra roots (theme overrides, etc.)
        foreach ($this->extraRoots as $r) {
            $r = rtrim($r, DIRECTORY_SEPARATOR);
            $candidateNames[] = $r . '/' . $pascal . '/' . $templateSlug . '.mustache';
            $candidateNames[] = $r . '/' . strtolower($componentSlug) . '/' . $templateSlug . '.mustache';
        }

        foreach ($candidateNames as $file) {
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    protected function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($value))));
    }

    protected function withSuppressedMustacheDeprecations(callable $callback)
    {
        if (!$this->suppressMustacheDeprecations) {
            return $callback();
        }
        $previousHandler = set_error_handler(function ($errno, $errstr, $errfile = null, $errline = null) use (&$previousHandler) {
            if ($errno & E_DEPRECATED) {
                // Suppress known Mustache library PHP 8.4 deprecations (implicit nullable parameters etc.)
                $isMustacheFile = $errfile && str_contains($errfile, '/mustache/mustache/');
                $isMustacheMessage = str_contains($errstr, 'Mustache_');
                if ($isMustacheFile || $isMustacheMessage) {
                    return true; // Swallow
                }
            }
            if ($previousHandler) {
                return $previousHandler($errno, $errstr, $errfile, $errline);
            }
            return false; // Let PHP handle
        });
        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /** Set suppression of Mustache deprecation warnings */
    public function suppressMustacheDeprecations(bool $suppress = true): void
    {
        $this->suppressMustacheDeprecations = $suppress;
    }

    /**
     * Convenience: render the default theme header fragment.
     * Accepts optional data (page_title, site_name, nav, user, etc.).
     */
    public function header(array|object $data = []): string
    {
        $arr = (array)$data;
        // Auto inject nav & drawer_items if absent
        if (!array_key_exists('nav', $arr)) {
            try { $arr['nav'] = \DevFramework\Core\Theme\NavigationManager::getInstance()->getNav(); } catch (\Throwable $e) { $arr['nav'] = []; }
        }
        if (!array_key_exists('drawer_items', $arr)) {
            try { $arr['drawer_items'] = \DevFramework\Core\Theme\NavigationManager::getInstance()->getDrawer(); } catch (\Throwable $e) { $arr['drawer_items'] = []; }
        }
        $isAuth = false;
        try {
            if (class_exists('DevFramework\\Core\\Auth\\AuthenticationManager')) {
                $am = \DevFramework\Core\Auth\AuthenticationManager::getInstance();
                if ($am->isAuthenticated()) {
                    $isAuth = true;
                    if (!isset($arr['logout_url'])) { $arr['logout_url'] = '/login/logout.php'; }
                    if (!isset($arr['user']) || empty($arr['user'])) {
                        $u = $am->getCurrentUser();
                        if ($u) { $arr['user'] = ['username' => $u->getUsername()]; }
                    }
                } else {
                    if (!isset($arr['login_url'])) { $arr['login_url'] = '/login/login.php'; }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
        $arr['is_authenticated'] = $isAuth;
        return $this->renderFromTemplate('theme_header', $arr);
    }

    /**
     * Convenience: render the default theme footer fragment.
     * Accepts optional data (footer_links, current_year, extra_footer, etc.).
     */
    public function footer(array|object $data = []): string
    {
        // Ensure current_year default if not provided
        if (is_array($data)) {
            $data['current_year'] = $data['current_year'] ?? date('Y');
        } elseif (is_object($data) && !isset($data->current_year)) {
            $data->current_year = date('Y');
        }
        return $this->renderFromTemplate('theme_footer', (array)$data);
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
