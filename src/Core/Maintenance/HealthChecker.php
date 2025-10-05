<?php
namespace DevFramework\Core\Maintenance;

/**
 * HealthChecker aggregates lightweight runtime / environment status data
 * for both JSON API (/health) and the admin UI page.
 */
class HealthChecker
{
    /**
     * Gather health data.
     * @return array{status:string,timestamp:string,services:array,php_extensions:array}
     */
    public static function gather(): array
    {
        $data = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'services' => [],
            'php_extensions' => []
        ];
        try {
            // MySQL presence (basic heuristic: env var set)
            if (getenv('MYSQL_HOST') || isset($_ENV['MYSQL_HOST'])) {
                $data['services']['mysql'] = 'available';
            } else {
                $data['services']['mysql'] = 'unknown';
            }
            // Redis extension
            if (class_exists('Redis')) {
                $data['services']['redis'] = 'extension_loaded';
            } else {
                $data['services']['redis'] = 'not_loaded';
            }
            // PHP extensions (boolean map for compactness)
            $exts = get_loaded_extensions();
            sort($exts, SORT_STRING | SORT_FLAG_CASE);
            foreach ($exts as $ext) { $data['php_extensions'][$ext] = true; }
        } catch (\Throwable $e) {
            $data['status'] = 'degraded';
            $data['error'] = $e->getMessage();
        }
        return $data;
    }
}

