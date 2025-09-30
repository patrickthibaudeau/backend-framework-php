<?php

namespace DevFramework\Core\Maintenance;

/**
 * Maintenance Mode Manager
 * Handles putting the system into maintenance mode during upgrades
 */
class MaintenanceMode
{
    private string $maintenanceFile;
    private string $lockFile;

    public function __construct()
    {
        $this->maintenanceFile = storage_path('framework/maintenance.json');
        $this->lockFile = storage_path('framework/maintenance.lock');

        // Ensure storage directory exists
        $storageDir = dirname($this->maintenanceFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
    }

    /**
     * Enable maintenance mode
     */
    public function enable(string $reason = 'System maintenance', ?int $duration = null): bool
    {
        try {
            $maintenanceData = [
                'enabled' => true,
                'reason' => $reason,
                'started_at' => time(),
                'estimated_duration' => $duration,
                'process_id' => getmypid(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'localhost'
            ];

            // Create maintenance file
            $result = file_put_contents(
                $this->maintenanceFile,
                json_encode($maintenanceData, JSON_PRETTY_PRINT),
                LOCK_EX
            );

            if ($result !== false) {
                // Create lock file for process tracking
                touch($this->lockFile);
                logger("Maintenance mode enabled: {$reason}", 'info');
                return true;
            }

            return false;
        } catch (\Exception $e) {
            logger("Failed to enable maintenance mode: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Disable maintenance mode
     */
    public function disable(): bool
    {
        try {
            $wasEnabled = $this->isEnabled();

            // Remove maintenance files
            if (file_exists($this->maintenanceFile)) {
                unlink($this->maintenanceFile);
            }
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }

            if ($wasEnabled) {
                logger("Maintenance mode disabled", 'info');
            }

            return true;
        } catch (\Exception $e) {
            logger("Failed to disable maintenance mode: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check if maintenance mode is enabled
     */
    public function isEnabled(): bool
    {
        return file_exists($this->maintenanceFile);
    }

    /**
     * Get maintenance mode information
     */
    public function getInfo(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $content = file_get_contents($this->maintenanceFile);
            $data = json_decode($content, true);

            if ($data && isset($data['started_at'])) {
                $data['duration'] = time() - $data['started_at'];
                $data['started_at_readable'] = date('Y-m-d H:i:s', $data['started_at']);

                if (isset($data['estimated_duration'])) {
                    $data['estimated_end'] = $data['started_at'] + $data['estimated_duration'];
                    $data['estimated_end_readable'] = date('Y-m-d H:i:s', $data['estimated_end']);
                    $data['time_remaining'] = max(0, $data['estimated_end'] - time());
                }
            }

            return $data;
        } catch (\Exception $e) {
            logger("Failed to get maintenance info: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Check if current request should be allowed during maintenance
     */
    public function isAllowed(): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        // Always allow CLI access
        if (php_sapi_name() === 'cli') {
            return true;
        }

        // Allow specific IP addresses (can be configured)
        $allowedIps = env('MAINTENANCE_ALLOWED_IPS', '');
        if ($allowedIps) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $allowedList = array_map('trim', explode(',', $allowedIps));
            if (in_array($currentIp, $allowedList)) {
                return true;
            }
        }

        // Allow bypass with secret key
        $bypassKey = env('MAINTENANCE_BYPASS_KEY', '');
        if ($bypassKey && isset($_GET['bypass']) && $_GET['bypass'] === $bypassKey) {
            return true;
        }

        return false;
    }

    /**
     * Display maintenance page and exit
     */
    public function displayMaintenancePage(): void
    {
        $info = $this->getInfo();
        $reason = $info['reason'] ?? 'System maintenance';
        $timeRemaining = $info['time_remaining'] ?? null;

        // Set appropriate HTTP status
        http_response_code(503);
        header('Retry-After: ' . ($timeRemaining ?? 3600)); // Default 1 hour

        // Check if this is an API request
        $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 ||
                 strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Service Unavailable',
                'message' => $reason,
                'maintenance' => true,
                'estimated_duration' => $timeRemaining,
                'retry_after' => $timeRemaining ?? 3600
            ], JSON_PRETTY_PRINT);
        } else {
            $this->displayMaintenanceHTML($reason, $timeRemaining);
        }

        exit;
    }

    /**
     * Display maintenance HTML page
     */
    private function displayMaintenanceHTML(string $reason, ?int $timeRemaining): void
    {
        $appName = env('APP_NAME', 'DevFramework Application');
        $timeMessage = '';

        if ($timeRemaining) {
            $hours = floor($timeRemaining / 3600);
            $minutes = floor(($timeRemaining % 3600) / 60);
            if ($hours > 0) {
                $timeMessage = "Estimated time remaining: {$hours}h {$minutes}m";
            } else {
                $timeMessage = "Estimated time remaining: {$minutes} minutes";
            }
        }

        // Build the time remaining HTML separately
        $timeRemainingHtml = '';
        if ($timeMessage) {
            $timeRemainingHtml = "<div class=\"time-remaining\">{$timeMessage}</div>";
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - {$appName}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .maintenance-container {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            margin: 2rem;
        }
        .maintenance-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .time-remaining {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            margin: 1rem 0;
        }
        .refresh-info {
            font-size: 0.9rem;
            color: #888;
        }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">🔧</div>
        <h1>System Under Maintenance</h1>
        <p><strong>{$reason}</strong></p>
        <p>We're currently performing system updates to improve your experience. Please check back in a few minutes.</p>
        {$timeRemainingHtml}
        <p class="refresh-info">This page will automatically refresh every 30 seconds.</p>
    </div>
</body>
</html>
HTML;
    }
}
