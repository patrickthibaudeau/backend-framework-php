<?php
namespace DevFramework\Core\Output;

use Exception;

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
            // Build engine lazily now
            $this->buildEngine();
        }

        $path = $this->resolveTemplatePath($componentSlug, $templateSlug);
        if (!$path || !is_file($path)) {
            throw new Exception("Template '{$templateName}' not found (searched for '{$path}').");
        }

        $source = file_get_contents($path);
        return $this->withSuppressedMustacheDeprecations(function () use ($source, $data) {
            return $this->engine->render($source, $data);
        });
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
        return $this->renderFromTemplate('theme_header', (array)$data);
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
