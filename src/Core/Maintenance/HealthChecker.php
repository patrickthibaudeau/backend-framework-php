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
            // MySQL presence detection
            // Previous implementation only looked at MYSQL_HOST which caused 'unknown' status
            // when only DB_HOST (used elsewhere in the framework) was defined.
            $mysqlHost = getenv('MYSQL_HOST');
            if ($mysqlHost === false || $mysqlHost === '') { $mysqlHost = getenv('DB_HOST'); }
            if (!$mysqlHost && isset($_ENV['MYSQL_HOST'])) { $mysqlHost = $_ENV['MYSQL_HOST']; }
            if (!$mysqlHost && isset($_ENV['DB_HOST'])) { $mysqlHost = $_ENV['DB_HOST']; }

            if ($mysqlHost) {
                // Basic heuristic: if a MySQL host is configured AND an appropriate extension is loaded, mark available.
                $hasDriver = extension_loaded('pdo_mysql') || extension_loaded('mysqli');
                if ($hasDriver) {
                    $data['services']['mysql'] = 'available';
                } else {
                    $data['services']['mysql'] = 'not_loaded'; // host configured but no driver extension
                }
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
