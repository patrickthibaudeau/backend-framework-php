# Access Control (RBAC) Overview

This document describes the role-based access control system added to the framework.

## Tables

Created during core install (prefixed automatically):
- capabilities (id, name, captype, component, timecreated, timemodified)
- roles (id, name, shortname, description, sortorder, timecreated, timemodified)
- role_capabilities (id, roleid, capability, permission, timecreated, timemodified)
- role_assignment (id, userid, roleid, component, timecreated, timemodified)

## Capability Declaration
Each component (core or module) may declare capabilities in `db/access.php`:
```php
$capabilities = [
    'auth:add' => ['captype' => 'write'],
    'auth:view' => ['captype' => 'read'],
];
```
Key format: `component:action`.

On bootstrap the framework scans all `access.php` files and syncs capabilities into the database (`capabilities` table). Re‑runs are idempotent.

## Automatic Seeding
If no roles exist when capabilities are synced:
1. An `Administrator` role (shortname: `admin`, sortorder 0) is created.
2. All known capabilities are granted as `allow` to this role.
3. The role is assigned to the first user (lowest id) if one exists.

## Permissions Semantics
Supported permission values:
- allow
- prevent
- prohibit (strongest deny, cannot be overridden)
- notset (default / ignored)

Evaluation order when calling `hasCapability('component:action', $userId)`:
1. Collect user role assignments matching either the exact component or global (NULL component).
2. Order: component-specific roles first, then global roles, each by ascending `sortorder` (lower = higher priority).
3. For a capability, first effective decision wins unless a later role specifies `prohibit`, which always overrides to deny.

## Caching
Per request, AccessManager caches:
- Evaluation map per (user, component)
- Final boolean decisions per (user, component, capability)

Cache is cleared automatically on capability sync or manually via:
```php
DevFramework\Core\Access\AccessManager::getInstance()->resetCache();
```

## Console Commands
A new `roles` command group is available from `console.php` (run inside the container):
```
php console.php roles list
php console.php roles create <shortname> <name> [--sortorder=N] [--description=...]
php console.php roles capabilities               # list all capabilities
php console.php roles capabilities <roleid|shortname>
php console.php roles grant <roleid|shortname> <capability> [--permission=allow]
php console.php roles revoke <roleid|shortname> <capability>
php console.php roles assign <userid> <roleid|shortname> [--component=component]
php console.php roles sync    # re-scan access.php and seed if needed
```

### Examples
Grant a capability:
```
php console.php roles grant admin auth:add --permission=allow
```
Assign role 2 to user 5 only for component `reports`:
```
php console.php roles assign 5 2 --component=reports
```
Revoke capability:
```
php console.php roles revoke admin auth:add
```
Resync capabilities after adding a new access.php entry:
```
php console.php roles sync
```

## Context-Specific Assignments
If a role is assigned with a `component` value, its permissions only apply when checking a capability whose component matches. These assignments are considered before global (NULL component) assignments.

## Adding New Capabilities
1. Create or edit `<component>/db/access.php`.
2. Add entries to `$capabilities` array.
3. Run: `php console.php roles sync` inside container.

## Helper Function
Use globally:
```php
if (hasCapability('auth:add', $USER->id)) {
    // permitted action
}
```

## Extending
Potential future enhancements:
- Web UI for managing roles
- Capability grouping or inheritance
- Export/import role definitions

---
This system is intentionally minimal but robust; contributions welcome.

---
## Admin UI (New)
A full administrative interface is available under `/admin/` (link appears automatically in navigation if the current user has `rbac:manage`).

### Capabilities for Admin Features
Added in `src/Core/Access/db/access.php`:
- `rbac:manage` (write) – required for managing roles/templates/capabilities
- `rbac:viewaudit` (read) – required to view the audit log
- `rbac:importexport` (write) – required to import/export role profiles

### Pages
| Page | Purpose | Required Capability |
|------|---------|---------------------|
| /admin/index.php | Dashboard overview | rbac:manage |
| /admin/roles.php | Create/edit roles, assign users, direct capability overrides & template assignment | rbac:manage |
| /admin/templates.php | Create/edit templates (capability inheritance) | rbac:manage |
| /admin/import_export.php | Batch export/import JSON role profiles | rbac:importexport |
| /admin/audit.php | View RBAC audit trail | rbac:viewaudit |

### Templates (Capability Inheritance)
Templates act as reusable capability bundles. A role may have multiple templates; their capability decisions are applied in the order the templates were assigned (first-assigned = higher priority) before explicit role capability overrides. Explicit role capabilities always override inherited template capabilities unless the earlier decision is `prohibit`.

Tables involved:
- role_templates
- role_template_capabilities
- role_template_assign

### Audit Logging
All RBAC-changing actions (role creation, capability grant/revoke, template changes, assignments, imports, etc.) are logged to `role_audit_log` with:
- actorid, userid (target), targetroleid, capability, action, details (JSON), ip, timestamp

View via `/admin/audit.php` with filtering (action, actor, role, capability) and pagination.

### Import / Export
`/admin/import_export.php` provides:
- Export: JSON file containing roles (optionally excluding Administrator) with capabilities & template references
- Import modes:
  - Merge: Adds new roles, updates existing, preserves unspecified capabilities/templates
  - Replace: Clears and replaces capabilities/templates for roles in payload
- Administrator (admin) role always enforced post-import (cannot remove admin user assignment)

### Navigation Integration
`src/Core/Theme/default/navigation.php` dynamically adds an “Admin” link to top nav & drawer only if the session user has `rbac:manage`.

### Admin Role Enforcement
Framework ensures the `admin` user always has the `admin` role:
- During capability sync (bootstrap) via `ensureAdminUserHasAdminRole()`
- Unassignment of admin user from Administrator role is prevented in the UI

### Security Notes
- CSRF tokens used on all modifying admin forms.
- Capability gates at page level; granular feature separation via dedicated capabilities.
- Audit log is append-only (no delete UI provided).

### JSON Export Structure (Example)
```json
{
  "exported_at": "2025-10-05T12:34:56Z",
  "include_admin": true,
  "roles": [
    {
      "shortname": "editor",
      "name": "Editor",
      "description": "Can edit content",
      "sortorder": 50,
      "capabilities": [ { "name": "auth:view", "permission": "allow" } ],
      "templates": ["content_base"]
    }
  ]
}
```

### Operational Checklist
1. Add or modify capability definitions in any component `db/access.php`.
2. Run bootstrap (or `php console.php roles sync` inside container) to sync.
3. Use Admin UI to create templates and roles, then assign capabilities or inherit templates.
4. Export role profiles before large changes; import as needed.
5. Review `/admin/audit.php` for change history.

---
End of extended RBAC documentation.
