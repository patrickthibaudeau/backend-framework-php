<?php
namespace DevFramework\Core\Notifications;

/**
 * NotificationManager
 * Lightweight framework-wide notification (flash message) system.
 *
 * Features:
 * - Supports success, error, warning, info, debug message types
 * - Stores notifications in PHP session (persists across redirects)
 * - Falls back to in-memory store when running in CLI (no session)
 * - Tailwind CSS friendly render method producing utility-class based alerts
 * - Helper methods for quick usage: notification()->success('Saved!') etc.
 */
class NotificationManager
{
    /** @var NotificationManager|null */
    protected static ?NotificationManager $instance = null;

    /**
     * Session key used to store notifications.
     */
    private const SESSION_KEY = '__df_notifications';

    /**
     * In-memory (CLI) storage fallback.
     * @var array<int,array<string,mixed>>
     */
    private array $cliStore = [];

    /**
     * Returns singleton instance.
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /** Ensure a session has started when not in CLI. */
    private function ensureSession(): void
    {
        if (PHP_SAPI === 'cli') {
            return; // Do not start sessions in CLI context
        }
        if (session_status() === PHP_SESSION_NONE) {
            // Suppress headers already sent warnings gracefully
            @session_start();
        }
    }

    /** Add a generic notification. */
    public function add(string $type, string $message, array $options = []): void
    {
        $allowed = ['success','error','warning','info','debug'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }

        $note = [
            'id'          => $options['id'] ?? bin2hex(random_bytes(6)),
            'type'        => $type,
            'message'     => $message,
            'title'       => $options['title'] ?? null,
            'dismissible' => $options['dismissible'] ?? true,
            'data'        => $options['data'] ?? null,
            'timestamp'   => time(),
        ];

        if (PHP_SAPI === 'cli') {
            $this->cliStore[] = $note;
            return;
        }

        $this->ensureSession();
        $_SESSION[self::SESSION_KEY] = $_SESSION[self::SESSION_KEY] ?? [];
        $_SESSION[self::SESSION_KEY][] = $note;
    }

    // Convenience helpers
    public function success(string $message, array $options = []): void { $this->add('success', $message, $options); }
    public function error(string $message, array $options = []): void { $this->add('error', $message, $options); }
    public function warning(string $message, array $options = []): void { $this->add('warning', $message, $options); }
    public function info(string $message, array $options = []): void { $this->add('info', $message, $options); }
    public function debug(string $message, array $options = []): void { $this->add('debug', $message, $options); }

    /**
     * Retrieve all notifications.
     * @param bool $consume Whether to remove them after retrieval (flash behavior)
     * @return array<int,array<string,mixed>>
     */
    public function all(bool $consume = true): array
    {
        if (PHP_SAPI === 'cli') {
            $items = $this->cliStore;
            if ($consume) { $this->cliStore = []; }
            return $items;
        }
        $this->ensureSession();
        $items = $_SESSION[self::SESSION_KEY] ?? [];
        if ($consume) {
            unset($_SESSION[self::SESSION_KEY]);
        }
        return $items;
    }

    /** Clear all stored notifications. */
    public function clear(): void
    {
        if (PHP_SAPI === 'cli') {
            $this->cliStore = [];
            return;
        }
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    /** Number of notifications waiting. */
    public function count(): int
    {
        if (PHP_SAPI === 'cli') {
            return count($this->cliStore);
        }
        $this->ensureSession();
        return isset($_SESSION[self::SESSION_KEY]) ? count($_SESSION[self::SESSION_KEY]) : 0;
    }

    /**
     * Render notifications as Tailwind styled alert components.
     * @param bool $consume Flash consume after render
     * @return string HTML string (safe to echo)
     */
    public function render(bool $consume = true): string
    {
        $items = $this->all($consume);
        if (empty($items)) {
            return '';
        }

        $typeClasses = [
            'success' => 'bg-green-50 border-green-200 text-green-800',
            'error'   => 'bg-red-50 border-red-200 text-red-800',
            'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
            'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
            'debug'   => 'bg-gray-50 border-gray-200 text-gray-800',
        ];

        // Simplified icon map using accessible emoji (avoids SVG attribute warnings)
        $iconMap = [
            'success' => '<span aria-hidden="true">✅</span>',
            'error'   => '<span aria-hidden="true">❌</span>',
            'warning' => '<span aria-hidden="true">⚠️</span>',
            'info'    => '<span aria-hidden="true">ℹ️</span>',
            'debug'   => '<span aria-hidden="true">🛠️</span>',
        ];

        $html = "<div class=\"df-notifications space-y-3\">";
        foreach ($items as $note) {
            $type = $note['type'];
            $classes = $typeClasses[$type] ?? $typeClasses['info'];
            $icon = $iconMap[$type] ?? '';
            $titleHtml = $note['title'] ? '<h4 class="font-semibold mb-1">' . htmlspecialchars($note['title']) . '</h4>' : '';
            $dismissBtn = '';
            if ($note['dismissible']) {
                $dismissBtn = '<button type="button" class="ml-4 text-sm text-current/70 hover:text-current focus:outline-none" onclick="this.closest(\'div.df-alert\').remove()" aria-label="Dismiss">&times;</button>';
            }
            $html .= '<div class="df-alert border rounded-lg p-4 flex items-start gap-3 ' . $classes . '">'
                  . '<div class="shrink-0 text-lg leading-none">' . $icon . '</div>'
                  . '<div class="flex-1 min-w-0">' . $titleHtml . '<div class="text-sm leading-relaxed">' . nl2br(htmlspecialchars($note['message'])) . '</div></div>'
                  . ($dismissBtn ? '<div class="shrink-0 mt-1">' . $dismissBtn . '</div>' : '')
                  . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
