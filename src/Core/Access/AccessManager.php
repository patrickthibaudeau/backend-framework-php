<?php
namespace DevFramework\Core\Access;

use DevFramework\Core\Database\Database;

/**
 * AccessManager handles capability registration and permission checks.
 */
class AccessManager
{
    public const PERMISSION_NOTSET = 'notset';
    public const PERMISSION_ALLOW = 'allow';
    public const PERMISSION_PREVENT = 'prevent';
    public const PERMISSION_PROHIBIT = 'prohibit';

    private static ?AccessManager $instance = null;
    private Database $db;

    /**
     * Per-request caches
     */
    private array $permissionCache = []; // [userId][component][capability] => bool
    private array $evaluationCache = []; // [userId][component] => [capability => permissionString]

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): AccessManager
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Reset all caches */
    public function resetCache(): void
    {
        $this->permissionCache = [];
        $this->evaluationCache = [];
    }

    /**
     * Discover all access.php files across core and modules.
     * @return array List of file paths
     */
    private function discoverAccessFiles(): array
    {
        $paths = [];
        // Core components under src/Core/*/db/access.php
        $coreDir = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($coreDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'access.php' && str_contains($file->getPath(), DIRECTORY_SEPARATOR . 'db')) {
                $paths[] = $file->getPathname();
            }
        }
        // Modules: modules/*/db/access.php
        $modulesDir = dirname(__DIR__, 2) . '/modules';
        if (is_dir($modulesDir)) {
            foreach (glob($modulesDir . '/*/db/access.php') as $mfile) {
                $paths[] = $mfile;
            }
        }
        return $paths;
    }

    /**
     * Synchronize all capabilities declared in access.php files into the database.
     * Also seeds default Administrator role (admin) if roles table empty.
     */
    public function syncAllCapabilities(): void
    {
        $this->resetCache();
        try {
            $files = $this->discoverAccessFiles();
            $pdo = $this->db->getConnection();
            $capTable = $this->db->addPrefix('capabilities');
            $now = time();

            $insertStmt = $pdo->prepare("INSERT INTO `{$capTable}` (name, captype, component, timecreated, timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE captype=VALUES(captype), component=VALUES(component), timemodified=VALUES(timemodified)");

            foreach ($files as $file) {
                $capabilities = [];
                try {
                    include $file; // expects $capabilities array
                } catch (\Throwable $e) {
                    error_log('AccessManager: Failed including ' . $file . ' - ' . $e->getMessage());
                    continue;
                }
                if (!is_array($capabilities) || empty($capabilities)) { continue; }

                foreach ($capabilities as $name => $def) {
                    if (!is_string($name) || !is_array($def)) { continue; }
                    if (!isset($def['captype'])) { continue; }
                    $parts = explode(':', $name, 2);
                    if (count($parts) !== 2) { continue; }
                    $component = $parts[0];
                    $captype = $def['captype'];
                    try {
                        $insertStmt->execute([$name, $captype, $component, $now, $now]);
                    } catch (\Throwable $e) {
                        error_log('AccessManager: Insert capability failed ' . $name . ' - ' . $e->getMessage());
                    }
                }
            }

            $this->seedDefaultAdministratorRole();
        } catch (\Throwable $e) {
            error_log('AccessManager: syncAllCapabilities fatal error: ' . $e->getMessage());
        }
    }

    /**
     * Seed default Administrator role (shortname 'admin') if no roles exist.
     * Assign all capabilities as ALLOW and bind to first existing user (smallest id).
     */
    private function seedDefaultAdministratorRole(): void
    {
        try {
            $pdo = $this->db->getConnection();
            $rolesTable = $this->db->addPrefix('roles');
            $roleCapsTable = $this->db->addPrefix('role_capabilities');
            $assignTable = $this->db->addPrefix('role_assignment');
            $capsTable = $this->db->addPrefix('capabilities');
            $usersTable = $this->db->addPrefix('users');

            // Any roles already?
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$rolesTable}`")->fetchColumn();
            if ($count > 0) { return; }

            $time = time();
            $stmt = $pdo->prepare("INSERT INTO `{$rolesTable}` (name, shortname, description, sortorder, timecreated, timemodified) VALUES (?,?,?,?,?,?)");
            $stmt->execute(['Administrator', 'admin', 'System super user with all permissions', 0, $time, $time]);
            $adminRoleId = (int)$pdo->lastInsertId();
            if (!$adminRoleId) { return; }

            // Fetch all capabilities
            $capNames = $pdo->query("SELECT name FROM `{$capsTable}`")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            $capStmt = $pdo->prepare("INSERT INTO `{$roleCapsTable}` (roleid, capability, permission, timecreated, timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission), timemodified=VALUES(timemodified)");
            foreach ($capNames as $capName) {
                try { $capStmt->execute([$adminRoleId, $capName, self::PERMISSION_ALLOW, $time, $time]); } catch (\Throwable $e) { /* ignore */ }
            }

            // Assign to first user if exists
            $firstUserId = $pdo->query("SELECT id FROM `{$usersTable}` ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($firstUserId) {
                $assignStmt = $pdo->prepare("INSERT INTO `{$assignTable}` (userid, roleid, component, timecreated, timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE timemodified=VALUES(timemodified)");
                $assignStmt->execute([(int)$firstUserId, $adminRoleId, null, $time, $time]);
            }
            error_log('AccessManager: Seeded default Administrator role and assignment');
        } catch (\Throwable $e) {
            error_log('AccessManager: Failed seeding Administrator role: ' . $e->getMessage());
        }
    }

    /**
     * Build evaluation cache for a user/component context (includes global + component-specific).
     * Component-specific assignments override global by ordering.
     */
    private function buildEvaluationCache(int $userId, string $component): void
    {
        $pdo = $this->db->getConnection();
        $rolesTable = $this->db->addPrefix('roles');
        $assignTable = $this->db->addPrefix('role_assignment');
        $rcapTable = $this->db->addPrefix('role_capabilities');

        $sql = "SELECT r.id as roleid, r.sortorder, rc.capability, rc.permission, ra.component AS assignment_component
                FROM {$rolesTable} r
                INNER JOIN {$assignTable} ra ON ra.roleid = r.id AND ra.userid = :userid
                LEFT JOIN {$rcapTable} rc ON rc.roleid = r.id
                WHERE (ra.component IS NULL OR ra.component = :component)
                ORDER BY (ra.component IS NULL) ASC, r.sortorder ASC"; // component-specific first, then global by sortorder
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':userid' => $userId, ':component' => $component]);

        $this->evaluationCache[$userId][$component] = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cap = $row['capability'];
            if (!$cap) { continue; }
            $perm = $row['permission'] ?: self::PERMISSION_NOTSET;

            // Skip if not set
            if ($perm === self::PERMISSION_NOTSET) { continue; }

            // If already decided by a higher-priority role, skip unless current is PROHIBIT (strongest deny)
            if (isset($this->evaluationCache[$userId][$component][$cap])) {
                // Existing permission present
                if ($this->evaluationCache[$userId][$component][$cap] === self::PERMISSION_PROHIBIT) {
                    continue; // can't override prohibit
                }
                if ($perm === self::PERMISSION_PROHIBIT) {
                    $this->evaluationCache[$userId][$component][$cap] = $perm; // escalate to prohibit
                }
                // else keep original (higher-priority) decision
                continue;
            }
            $this->evaluationCache[$userId][$component][$cap] = $perm;
        }
    }

    /**
     * Check if user has a capability.
     * Algorithm:
     * 1. Extract component from capability name (before ':').
     * 2. Build evaluation cache (ordered: component-specific role assignments, then global) if not built.
     * 3. Determine permission applying precedence: prohibit > allow > prevent > notset.
     * 4. Return boolean (allow = true; others = false).
     */
    public function userHasCapability(string $capability, int $userId): bool
    {
        if ($userId <= 0 || $capability === '') { return false; }
        $parts = explode(':', $capability, 2);
        if (count($parts) !== 2) { return false; }
        $component = $parts[0];

        // Permission result cache
        if (isset($this->permissionCache[$userId][$component][$capability])) {
            return $this->permissionCache[$userId][$component][$capability];
        }

        if (!isset($this->evaluationCache[$userId][$component])) {
            $this->buildEvaluationCache($userId, $component);
        }

        $perm = $this->evaluationCache[$userId][$component][$capability] ?? self::PERMISSION_NOTSET;
        $result = false;
        if ($perm === self::PERMISSION_PROHIBIT) { $result = false; }
        elseif ($perm === self::PERMISSION_ALLOW) { $result = true; }
        elseif ($perm === self::PERMISSION_PREVENT) { $result = false; }
        else { $result = false; }

        $this->permissionCache[$userId][$component][$capability] = $result;
        return $result;
    }
}
