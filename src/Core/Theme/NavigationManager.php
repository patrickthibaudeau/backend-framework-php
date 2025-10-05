<?php
namespace DevFramework\Core\Theme;

/**
 * Central manager for default navigation and drawer items.
 * Provides automatic loading from theme navigation config and helper methods
 * to override, merge, or extend navigation at runtime per request.
 */
class NavigationManager
{
    private static ?NavigationManager $instance = null;
    private bool $initialized = false;
    private array $nav = [];
    private array $drawer = [];

    private bool $cacheEnabled = false;
    private int $cacheTtl = 300; // seconds
    private string $cacheKey = 'devframework:theme:nav-config';
    private ?string $configFile = null;
    private int $configFileMtime = 0;

    private function __construct() {
        $this->detectCacheSettings();
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function detectCacheSettings(): void
    {
        // Disable via env NAV_CACHE_DISABLE=1
        $disabledFlag = getenv('NAV_CACHE_DISABLE');
        if ($disabledFlag !== false) {
            $norm = strtolower(trim($disabledFlag));
            if (in_array($norm, ['1','true','yes','on'], true)) {
                $this->cacheEnabled = false;
                return;
            }
        }
        $this->cacheEnabled = function_exists('apcu_enabled') && apcu_enabled();
        $ttl = getenv('NAV_CACHE_TTL');
        if ($ttl !== false && ctype_digit($ttl)) {
            $this->cacheTtl = (int)$ttl;
        }
    }

    private function initialize(): void
    {
        if ($this->initialized) { return; }
        $this->initialized = true;
        $root = defined('DIRROOT') ? DIRROOT : dirname(__DIR__, 3);
        $this->configFile = $root . '/src/Core/Theme/default/navigation.php';
        $this->configFileMtime = is_file($this->configFile) ? (int)@filemtime($this->configFile) : 0;

        // Attempt cache fetch
        if ($this->cacheEnabled) {
            $cached = apcu_fetch($this->cacheKey, $success);
            if ($success && is_array($cached)) {
                $cachedMtime = $cached['mtime'] ?? 0;
                if ($cachedMtime === $this->configFileMtime) {
                    $this->nav = $cached['nav'] ?? [];
                    $this->drawer = $cached['drawer'] ?? [];
                    return; // loaded from cache
                }
            }
        }

        // Load from file fallback
        if ($this->configFile && is_file($this->configFile)) {
            $data = include $this->configFile;
            if (is_array($data)) {
                $this->nav = $data['nav'] ?? [];
                $this->drawer = $data['drawer'] ?? [];
            }
        }

        $this->storeCache();
    }

    private function storeCache(): void
    {
        if (!$this->cacheEnabled) { return; }
        $payload = [
            'mtime' => $this->configFileMtime,
            'nav' => $this->nav,
            'drawer' => $this->drawer,
            'ts' => time()
        ];
        @apcu_store($this->cacheKey, $payload, $this->cacheTtl);
    }

    private function invalidateCache(): void
    {
        if ($this->cacheEnabled) {
            @apcu_delete($this->cacheKey);
        }
    }

    /** Get default top navigation items (with active detection). */
    public function getNav(): array
    {
        $this->initialize();
        return $this->applyActive($this->nav);
    }

    /** Get default drawer items (with active detection). */
    public function getDrawer(): array
    {
        $this->initialize();
        return $this->applyActive($this->drawer);
    }

    /** Override or merge top nav */
    public function setNav(array $items, bool $merge = false): void
    {
        $this->initialize();
        $this->nav = $merge ? array_merge($this->nav, $items) : $items;
        $this->storeCache();
    }

    public function addNavItem(array $item): void
    {
        $this->initialize();
        $this->nav[] = $item;
        $this->storeCache();
    }

    /** Override or merge drawer */
    public function setDrawer(array $items, bool $merge = false): void
    {
        $this->initialize();
        $this->drawer = $merge ? array_merge($this->drawer, $items) : $items;
        $this->storeCache();
    }

    public function addDrawerItem(array $item): void
    {
        $this->initialize();
        $this->drawer[] = $item;
        $this->storeCache();
    }

    /** Batch configure both with optional merging */
    public function configure(?array $nav = null, ?array $drawer = null, bool $merge = false): void
    {
        if ($nav !== null) { $this->setNav($nav, $merge); }
        if ($drawer !== null) { $this->setDrawer($drawer, $merge); }
    }

    /** Simple active flag detection by comparing URL path to current script path */
    private function applyActive(array $items): array
    {
        $current = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
        $currentPath = parse_url($current, PHP_URL_PATH) ?: $current;
        foreach ($items as &$item) {
            if (!isset($item['active']) || $item['active'] === false) {
                $itemPath = isset($item['url']) ? parse_url($item['url'], PHP_URL_PATH) : null;
                if ($itemPath && $itemPath === $currentPath) {
                    $item['active'] = true;
                }
            }
        }
        unset($item);
        return $items;
    }

    public function clearCache(): void
    {
        $this->invalidateCache();
        $this->initialized = false; // force reload next call
    }
}
