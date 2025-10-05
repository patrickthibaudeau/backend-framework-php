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

