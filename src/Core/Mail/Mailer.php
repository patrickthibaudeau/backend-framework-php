<?php
namespace DevFramework\Core\Mail;

use RuntimeException;

/**
 * Central mailer orchestrating driver selection and message dispatch.
 */
class Mailer
{
    private static ?Mailer $instance = null;
    private MailDriverInterface $driver;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->driver = $this->createDriver($config);
    }

    public static function getInstance(?array $config = null): self
    {
        if (!self::$instance) {
            if ($config === null) {
                $config = function_exists('config') ? (array)config('mail', []) : [];
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function _resetForTests(): void { self::$instance = null; }

    private function createDriver(array $config): MailDriverInterface
    {
        $raw = $config['driver'] ?? 'log';
        // Normalize and strip inline comments (e.g., "smtp  # comment")
        $rawStr = (string)$raw;
        if (str_contains($rawStr, '#')) {
            $rawStr = substr($rawStr, 0, strpos($rawStr, '#'));
        }
        $driver = strtolower(trim($rawStr));
        if ($driver === '' || $driver === null) {
            $driver = 'log';
        }
        // Normalize legacy aliases
        if ($driver === 'native') { $driver = 'mail'; }
        $defaultLog = function_exists('storage_path') ? storage_path('logs/mail.log') : sys_get_temp_dir().'/mail.log';
        $logPath = isset($config['log_path']) && trim((string)$config['log_path']) !== '' ? (string)$config['log_path'] : $defaultLog;
        return match($driver) {
            'log' => new \DevFramework\Core\Mail\LogMailDriver($logPath),
            'mail' => new \DevFramework\Core\Mail\NativeMailDriver(),
            'smtp' => new \DevFramework\Core\Mail\SmtpMailDriver($config['smtp'] ?? [], ['app_url' => (function_exists('config') ? (config('app.url') ?? null) : null)]),
            default => new \DevFramework\Core\Mail\LogMailDriver($logPath)
        };
    }

    /**
     * Create a new message prefilled with defaults (from, reply-to).
     */
    public function newMessage(): MailMessage
    {
        $m = new MailMessage();
        if (!empty($this->config['from']['address'])) {
            $m->from($this->config['from']['address'], $this->config['from']['name'] ?? null);
        }
        if (!empty($this->config['reply_to']['address'])) {
            $m->replyTo($this->config['reply_to']['address'], $this->config['reply_to']['name'] ?? null);
        }
        return $m;
    }

    /**
     * Send a message. Accepts either a ready MailMessage or a closure to configure one.
     * @param MailMessage|callable $messageOrConfigurator
     */
    public function send(MailMessage|callable $messageOrConfigurator): array
    {
        $message = $messageOrConfigurator instanceof MailMessage ? $messageOrConfigurator : $this->configure($messageOrConfigurator);
        if (empty($message->to)) {
            return ['success'=>false,'error'=>'No recipients'];
        }
        return $this->driver->send($message);
    }

    private function configure(callable $configurator): MailMessage
    {
        $msg = $this->newMessage();
        $configurator($msg);
        return $msg;
    }

    /** Quick helper for one-off simple emails */
    public function simple(string $to, string $subject, string $body, bool $isHtml = false): array
    {
        $msg = $this->newMessage()->to($to)->subject($subject);
        $isHtml ? $msg->html($body) : $msg->text($body);
        return $this->send($msg);
    }

    public function getDriverName(): string
    {
        $raw = $this->config['driver'] ?? 'log';
        $rawStr = (string)$raw;
        if (str_contains($rawStr, '#')) { $rawStr = substr($rawStr, 0, strpos($rawStr, '#')); }
        $driver = strtolower(trim($rawStr));
        if ($driver === '' || $driver === null) { $driver = 'log'; }
        if ($driver === 'native') { $driver = 'mail'; }
        return $driver;
    }
}
