<?php
namespace DevFramework\Core\Console;

use DevFramework\Core\Database\Database;
use DevFramework\Core\Access\AccessManager;

class RolesCommand
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function handle(array $args): void
    {
        if (empty($args)) { $this->showHelp(); return; }
        $command = array_shift($args);
        return match($command) {
            'list' => $this->listRoles(),
            'create' => $this->createRole($args),
            'capabilities' => $this->listCapabilities($args),
            'grant' => $this->grantCapability($args),
            'revoke' => $this->revokeCapability($args),
            'assign' => $this->assignRole($args),
            'sync' => $this->syncCapabilities(),
            default => $this->showHelp(),
        };
    }

    private function showHelp(): void
    {
        echo "Role Management Commands:\n";
        echo "  roles list                               List roles\n";
        echo "  roles create <shortname> <name> [--sortorder=N] [--description=...]\n";
        echo "  roles capabilities [roleid|shortname]    List capabilities (or all)\n";
        echo "  roles grant <roleid|shortname> <capability> [--permission=allow]\n";
        echo "  roles revoke <roleid|shortname> <capability>\n";
        echo "  roles assign <userid> <roleid|shortname> [--component=component]\n";
        echo "  roles sync                               Sync access.php capabilities into DB\n";
    }

    private function listRoles(): void
    {
        $table = $this->db->addPrefix('roles');
        $rows = $this->db->get_records_sql("SELECT * FROM {$table} ORDER BY sortorder ASC, id ASC");
        if (!$rows) { echo "No roles found.\n"; return; }
        printf("%-5s %-12s %-25s %-9s %s\n", 'ID','Shortname','Name','SortOrder','Description');
        foreach ($rows as $r) {
            printf("%-5d %-12s %-25s %-9d %s\n", $r->id, $r->shortname, $r->name, $r->sortorder, substr($r->description ?? '',0,60));
        }
    }

    private function resolveRoleId(string $idOrShort): ?int
    {
        if (ctype_digit($idOrShort)) { return (int)$idOrShort; }
        $table = $this->db->addPrefix('roles');
        $rec = $this->db->get_record_sql("SELECT id FROM {$table} WHERE shortname = ?", [$idOrShort]);
        return $rec->id ?? null;
    }

    private function createRole(array $args): void
    {
        if (count($args) < 2) { echo "Usage: roles create <shortname> <name> [--sortorder=N] [--description=...]\n"; return; }
        $shortname = array_shift($args);
        $name = array_shift($args);
        $options = $this->parseOptions($args);
        $sortorder = (int)($options['sortorder'] ?? 100);
        $description = $options['description'] ?? '';
        $table = $this->db->addPrefix('roles');

        // Check exists
        if ($this->db->record_exists('roles', ['shortname' => $shortname])) {
            echo "Role shortname already exists.\n"; return;
        }
        $now = time();
        $data = (object)[
            'name' => $name,
            'shortname' => $shortname,
            'description' => $description,
            'sortorder' => $sortorder,
            'timecreated' => $now,
            'timemodified' => $now
        ];
        $id = $this->db->insert_record('roles', $data, true);
        echo "Created role {$name} (ID {$id}).\n";
    }

    private function listCapabilities(array $args): void
    {
        $capTable = $this->db->addPrefix('capabilities');
        $roleCaps = $this->db->addPrefix('role_capabilities');

        if (empty($args)) {
            $caps = $this->db->get_records_sql("SELECT * FROM {$capTable} ORDER BY component, name");
            printf("%-35s %-8s %s\n", 'Capability','Type','Component');
            foreach ($caps as $c) {
                printf("%-35s %-8s %s\n", $c->name, $c->captype, $c->component);
            }
            return;
        }

        $roleId = $this->resolveRoleId($args[0]);
        if (!$roleId) { echo "Role not found.\n"; return; }
        $sql = "SELECT c.name, c.captype, rc.permission FROM {$capTable} c LEFT JOIN {$roleCaps} rc ON rc.capability = c.name AND rc.roleid = ? ORDER BY c.component, c.name";
        $caps = $this->db->get_records_sql($sql, [$roleId]);
        printf("%-35s %-8s %s\n", 'Capability','Type','Permission');
        foreach ($caps as $c) {
            printf("%-35s %-8s %s\n", $c->name, $c->captype, $c->permission ?? 'notset');
        }
    }

    private function grantCapability(array $args): void
    {
        if (count($args) < 2) { echo "Usage: roles grant <roleid|shortname> <capability> [--permission=allow]" . PHP_EOL; return; }
        $roleId = $this->resolveRoleId($args[0]);
        if (!$roleId) { echo "Role not found." . PHP_EOL; return; }
        $capability = $args[1];
        $options = $this->parseOptions(array_slice($args,2));
        $permission = $options['permission'] ?? AccessManager::PERMISSION_ALLOW;
        if (!in_array($permission, [AccessManager::PERMISSION_ALLOW, AccessManager::PERMISSION_PREVENT, AccessManager::PERMISSION_PROHIBIT, AccessManager::PERMISSION_NOTSET], true)) {
            echo "Invalid permission value." . PHP_EOL; return;
        }
        $capsTable = $this->db->addPrefix('capabilities');
        if (!$this->db->record_exists_sql("SELECT 1 FROM {$capsTable} WHERE name = ?", [$capability])) { echo "Capability not found." . PHP_EOL; return; }
        $roleCapsTable = $this->db->addPrefix('role_capabilities');
        $now = time();
        $sql = "INSERT INTO {$roleCapsTable} (roleid, capability, permission, timecreated, timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission), timemodified=VALUES(timemodified)";
        $this->db->execute($sql, [$roleId, $capability, $permission, $now, $now]);
        echo "Granted {$capability} => {$permission} to role {$roleId}." . PHP_EOL;
        AccessManager::getInstance()->resetCache();
    }

    private function revokeCapability(array $args): void
    {
        if (count($args) < 2) { echo "Usage: roles revoke <roleid|shortname> <capability>" . PHP_EOL; return; }
        $roleId = $this->resolveRoleId($args[0]);
        if (!$roleId) { echo "Role not found." . PHP_EOL; return; }
        $capability = $args[1];
        $roleCapsTable = $this->db->addPrefix('role_capabilities');
        $this->db->execute("DELETE FROM {$roleCapsTable} WHERE roleid = ? AND capability = ?", [$roleId, $capability]);
        echo "Revoked {$capability} from role {$roleId}." . PHP_EOL;
        AccessManager::getInstance()->resetCache();
    }

    private function assignRole(array $args): void
    {
        if (count($args) < 2) { echo "Usage: roles assign <userid> <roleid|shortname> [--component=component]" . PHP_EOL; return; }
        $userid = (int)$args[0];
        if ($userid <= 0) { echo "Invalid userid." . PHP_EOL; return; }
        $roleId = $this->resolveRoleId($args[1]);
        if (!$roleId) { echo "Role not found." . PHP_EOL; return; }
        $options = $this->parseOptions(array_slice($args,2));
        $component = $options['component'] ?? null;
        $assignTable = $this->db->addPrefix('role_assignment');
        $now = time();
        $sql = "INSERT INTO {$assignTable} (userid, roleid, component, timecreated, timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE timemodified=VALUES(timemodified)";
        $this->db->execute($sql, [$userid, $roleId, $component, $now, $now]);
        echo "Assigned role {$roleId} to user {$userid}" . ($component ? " for component {$component}" : '') . PHP_EOL;
        AccessManager::getInstance()->resetCache();
    }

    private function syncCapabilities(): void
    {
        AccessManager::getInstance()->syncAllCapabilities();
        echo "Capabilities synchronized." . PHP_EOL;
    }

    private function parseOptions(array $args): array
    {
        $opts = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                if (count($parts) === 2) { $opts[$parts[0]] = $parts[1]; }
            }
        }
        return $opts;
    }
}

