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
            $this->ensureAdminUserHasAdminRole();
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

    /** Ensure the user with username 'admin' always has the Administrator role */
    private function ensureAdminUserHasAdminRole(): void
    {
        try {
            $pdo = $this->db->getConnection();
            $rolesTable = $this->db->addPrefix('roles');
            $assignTable = $this->db->addPrefix('role_assignment');
            $usersTable = $this->db->addPrefix('users');
            $adminRoleId = $pdo->query("SELECT id FROM `{$rolesTable}` WHERE shortname='admin' LIMIT 1")->fetchColumn();
            if (!$adminRoleId) { return; }
            $adminUserId = $pdo->query("SELECT id FROM `{$usersTable}` WHERE username='admin' LIMIT 1")->fetchColumn();
            if (!$adminUserId) { return; }
            $stmt = $pdo->prepare("SELECT 1 FROM `{$assignTable}` WHERE userid=? AND roleid=? AND component IS NULL");
            $stmt->execute([$adminUserId, $adminRoleId]);
            if (!$stmt->fetchColumn()) {
                $time = time();
                $insert = $pdo->prepare("INSERT INTO `{$assignTable}` (userid, roleid, component, timecreated, timemodified) VALUES (?,?,?,?,?)");
                $insert->execute([$adminUserId, $adminRoleId, null, $time, $time]);
                $this->logAction($adminUserId, 'auto_assign_admin_role', ['roleid' => $adminRoleId]);
            }
        } catch (\Throwable $e) {
            error_log('AccessManager: ensureAdminUserHasAdminRole failed: ' . $e->getMessage());
        }
    }

    /** Log RBAC action into audit table */
    public function logAction(?int $actorId, string $action, array $details = [], ?int $targetUserId = null, ?int $targetRoleId = null, ?string $capability = null): void
    {
        try {
            $pdo = $this->db->getConnection();
            $auditLogTable = $this->db->addPrefix('role_audit_log');
            $sql = "INSERT INTO `{$auditLogTable}` (actorid, userid, targetroleid, capability, action, details, ip, timecreated) VALUES (?,?,?,?,?,?,?,?)";
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $pdo->prepare($sql)->execute([
                $actorId,
                $targetUserId,
                $targetRoleId,
                $capability,
                $action,
                json_encode($details, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                $ip,
                time()
            ]);
        } catch (\Throwable $e) {
            error_log('AccessManager: logAction failed: ' . $e->getMessage());
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

        $roleOrder = [];
        $roleCapabilities = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rid = (int)$row['roleid'];
            if (!in_array($rid, $roleOrder, true)) { $roleOrder[] = $rid; }
            if ($row['capability']) {
                $roleCapabilities[$rid][] = $row; // role-specific caps
            }
        }

        // Load template capabilities for roles
        $templateCapsMap = $this->loadTemplateCapabilitiesForRoles($roleOrder);

        $this->evaluationCache[$userId][$component] = [];

        // Iterate roles in computed order
        foreach ($roleOrder as $rid) {
            // Process template capabilities first (inheritance)
            if (isset($templateCapsMap[$rid])) {
                foreach ($templateCapsMap[$rid] as $tc) {
                    $this->applyCapabilityDecision($userId, $component, $tc['capability'], $tc['permission']);
                }
            }
            // Then role explicit capabilities (override templates)
            if (isset($roleCapabilities[$rid])) {
                foreach ($roleCapabilities[$rid] as $rc) {
                    $this->applyCapabilityDecision($userId, $component, $rc['capability'], $rc['permission']);
                }
            }
        }
    }

    /** Load template capabilities for a set of role IDs */
    private function loadTemplateCapabilitiesForRoles(array $roleIds): array
    {
        if (empty($roleIds)) { return []; }
        $pdo = $this->db->getConnection();
        $templateAssign = $this->db->addPrefix('role_template_assign');
        $templateCaps = $this->db->addPrefix('role_template_capabilities');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT rta.roleid, tc.capability, tc.permission, tc.templateid, rta.timecreated as assign_time
                FROM {$templateAssign} rta
                INNER JOIN {$templateCaps} tc ON tc.templateid = rta.templateid
                WHERE rta.roleid IN ($placeholders)
                ORDER BY rta.timecreated ASC, tc.templateid ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roleIds);
        $map = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $map[$row['roleid']][] = $row; // keep ordering
        }
        return $map;
    }

    private function applyCapabilityDecision(int $userId, string $component, string $capability, string $permission): void
    {
        if ($permission === self::PERMISSION_NOTSET) { return; }
        if (!isset($this->evaluationCache[$userId][$component][$capability])) {
            $this->evaluationCache[$userId][$component][$capability] = $permission;
            return;
        }
        $current = $this->evaluationCache[$userId][$component][$capability];
        if ($current === self::PERMISSION_PROHIBIT) { return; }
        if ($permission === self::PERMISSION_PROHIBIT) {
            $this->evaluationCache[$userId][$component][$capability] = $permission; // escalate
        }
        // otherwise keep earlier (higher priority) decision
    }

    /**
     * Public API used by the global hasCapability() helper.
     * Determines if a user has a capability (component:action) considering:
     *  - Component-specific role assignments first (by sortorder ASC)
     *  - Global role assignments next (by sortorder ASC)
     *  - Template capabilities (in assignment order) before explicit role caps
     *  - Prohibit strongest deny
     *  - allow => true, everything else => false when evaluation ends
     */
    public function userHasCapability(string $capability, int $userId): bool
    {
        if ($userId <= 0 || $capability === '') { return false; }
        $parts = explode(':', $capability, 2);
        if (count($parts) !== 2) { return false; }
        $component = $parts[0];

        // Cached final decision
        if (isset($this->permissionCache[$userId][$component][$capability])) {
            return $this->permissionCache[$userId][$component][$capability];
        }

        // Build evaluation cache for (user, component) if needed
        if (!isset($this->evaluationCache[$userId][$component])) {
            $this->buildEvaluationCache($userId, $component);
        }

        $perm = $this->evaluationCache[$userId][$component][$capability] ?? self::PERMISSION_NOTSET;
        $result = ($perm === self::PERMISSION_ALLOW); // only allow == true
        // All other states (prevent, prohibit, notset) => false
        $this->permissionCache[$userId][$component][$capability] = $result;
        return $result;
    }
}
