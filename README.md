# libinkk/permission

**Production-grade Laravel authorization engine** with RBAC, ABAC, multi-tenancy, temporary access, delegation, audit logs, explainable decisions, and layered caching.

> Authorization that explains itself.

Most permission packages answer: *Does this user have this permission?*

`libinkk/permission` answers:

> Is this user allowed to perform this action on this resource, in the current scope, under the current conditions — and if not, why?

```php
$user->can('invoice.approve', $invoice);
$user->explain('invoice.approve', $invoice);
```

---

## Why this package?

| Focus | What you get |
|-------|----------------|
| **Authorization engine** | Fail-closed decisions with structured reasons and sources |
| **Laravel-native** | Works with Gate, `$user->can()`, middleware, Blade `@can` / `@role` |
| **Explainable** | `explain()` and `authorizeFor()` return a full `Decision` object |
| **Performant** | Request memory → app cache → Redis → database |
| **Secure defaults** | Deny on failure; never trust frontend UI checks as a security boundary |
| **Extensible** | Contracts for engine, repositories, and cache |

This is **not** a thin roles CRUD helper. It is infrastructure for production authorization.

---

## Requirements

- PHP **8.1+**
- Laravel **10 / 11 / 12 / 13**

---

## Installation

```bash
composer require libinkk/permission
```

Publish the config (optional) or use the installer:

```bash
php artisan permission:install --migrate
# or
php artisan vendor:publish --tag=libinkk-permission-config
php artisan migrate
```

Add the trait to your authenticatable model:

```php
use Libinkk\Permission\Concerns\HasAuthorization;

class User extends Authenticatable
{
    use HasAuthorization;
}
```

The package does **not** own your `users` table. Assignments are polymorphic via `user_type` / `user_id`.

---

## Quick start

```php
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;

// Define a whole resource at once (or Permission::crud('posts'))
Permission::defineResource('posts', ['view', 'create', 'update', 'delete', 'publish'], [
    'group' => 'Posts',
]);

// Create a role and attach permissions (wildcards supported)
$role = Role::findOrCreate('editor');
$role->givePermissionTo('posts.*');

$user->assignRole('editor');
$user->givePermissionTo('reports.export');

$user->can('posts.update');           // true via posts.*
$user->hasRole('editor');             // true

// Kill feature: full roles + permissions export
$export = $user->exportAccess();
// totals, roles, direct, effective (wildcard-expanded), by_group, by_resource

$user->explain('posts.delete');
```

---

## Features

### Available now (v0.6)

- **Temporary access** — `$user->givePermissionTo('reports.export', expiresAt: now()->addDays(7))`
- **Scheduled grants** — `startsAt` / `expiresAt` on user permissions and roles
- **Delegation** — `$user->delegate('invoice.approve', to: $manager, until: now()->addHours(4))`
- **Audit log** — append-only `authorization_audits` (off by default)
- **Permission versioning** — snapshots on create/update, `$permission->rollbackTo(1)`
- **Permission history** — `$permission->history()` (versions + related audits)
- **Multi-tenancy** — tenant-scoped roles, isolation, optional package `tenants` table
- **Hierarchical scopes** — organization → workspace → project via `scopes.parent_id`
- **Authorization context** — `AuthorizationContext::tenant()` / `switch()` / `scope()`
- **Global vs tenant roles** — global roles cross tenants only when explicitly allowed
- **RBAC** — roles, role permissions, user ↔ role assignment
- **Direct permissions** — grant/revoke on the user
- **Role hierarchy** — `Role::inherit()`, cycle prevention, inherited permissions
- **Explicit deny** — `$user->denyPermissionTo()` / `$role->denyPermissionTo()` with configurable precedence
- **ABAC / conditions** — `Permission::define()->when(...)`, named `Condition::define()`
- **Ownership** — `.own` suffix + built-in `owner` condition
- **Permission resources** — `Permission::defineResource()`, `Permission::crud()`
- **Permission groups** — `group` metadata + `Permission::inGroup()`
- **Wildcards** — `posts.*`, `posts.view.*` resolve at decision time
- **Discovery** — PHP `#[Permission]` attributes + `permission:discover` / `sync`
- **Artisan DX** — install, resource, discover, sync, validate, doctor, cache, export
- **User access export** — `$user->exportAccess()` / `permission:export` (includes temporary + delegations)
- **Laravel Gate** — `Gate::before` integration for managed abilities
- **Middleware** — `permission:` and `role:` (configurable AND/OR)
- **Blade** — `@can`, `@role`, `@canany`, `@canall`, `@permissionPayload`
- **Vue / React** — `$can` / `usePermission()` / `<Can>` (UI-only)
- **Authorization API** — `GET /api/authorization`, `/api/users/{user}/access`, `/api/permissions/matrix`
- **Explainable decisions** — `Decision` + condition checks + deny layers
- **Layered cache** — request-level + store cache, Octane-safe flush
- **Multi-guard** — `guard_name` on roles/permissions and users
- **Configurable keys** — `bigint`, UUID, or ULID
- **Testing helpers** — `Permission::fake()`, `assertCan` / `assertCannot`
- **Events** — role/permission lifecycle, grant/revoke, delegation, policy, decisions

### Roadmap

| Version | Scope |
|---------|--------|
| **v0.1** | Roles, permissions, Gate, middleware, Blade, basic cache ✅ |
| **v0.2** | Resources, groups, wildcards, Artisan, discovery, validate, doctor, user export ✅ |
| **v0.3** | Conditions, ABAC, ownership, role hierarchy, explicit deny, explanation ✅ |
| **v0.4** | Tenants, hierarchical scopes, authorization context ✅ |
| **v0.5** | Temporary access, delegation, audit logs, versioning ✅ |
| **v0.6** | Vue / React adapters, authorization API payloads ✅ |
| **v0.7** | Optional Filament adapter (`libinkk/permission-filament`) |
| **v0.8+** | Debugger, graph, unused permissions, performance & security hardening |
| **v1.0** | Stable docs, upgrade guide, compatibility matrix |

Schema columns for scopes, expiration, and effects are already present where needed so later slices can land without breaking migrations.

---

## Permission naming

Use `resource.action`:

```text
posts.view
posts.create
posts.update
posts.delete
posts.publish
invoices.approve
reports.export
```

Permissions with a `.` are treated as package-managed abilities by the Gate integration.

### Resources & groups

```php
Permission::defineResource('posts', [
    'view', 'create', 'update', 'delete', 'publish',
], ['group' => 'Posts']);

Permission::crud('invoices'); // view/create/update/delete, group "Invoices"

Permission::define('reports.export', [
    'group' => 'Reports',
    'description' => 'Export reports',
]);

Permission::inGroup('Posts'); // Collection of permissions
```

```bash
php artisan permission:resource posts
php artisan permission:resource invoices --actions=view,approve,refund --group=Invoices
```

### Wildcards

```php
$role->givePermissionTo('posts.*');
$user->can('posts.view');   // true
$user->can('posts.delete'); // true

$user->givePermissionTo('posts.view.*');
$user->can('posts.view.own'); // true
```

Exact matches win over wildcards when both are present.

### Role hierarchy

```php
Role::inherit('admin', 'manager');   // admin inherits manager's permissions
Role::inherit('manager', 'editor');
Role::inherit('editor', 'viewer');

Role::uninherit('admin', 'manager'); // remove link
```

Circular inheritance is rejected and reported by `permission:validate`.

### Explicit deny

```php
$role->givePermissionTo('posts.delete');
$user->assignRole($role);
$user->denyPermissionTo('posts.delete'); // wins over role allow

$role->denyPermissionTo('posts.publish');
```

Default precedence: explicit deny → explicit allow → role deny → role allow → inherited deny → inherited allow → default deny.

### Conditions & ownership (ABAC)

```php
use Libinkk\Permission\Conditions\Condition;
use Libinkk\Permission\Permissions\Permission;

Condition::define('within_approval_limit', function ($user, $invoice) {
    return $invoice->amount <= $user->approval_limit;
});

Permission::define('invoice.approve')
    ->when('within_approval_limit');

Permission::define('posts.update.own')
    ->when(fn ($user, $post) => $post->author_id === $user->id);

// .own suffix also auto-checks ownership (author_id / user_id / owner_id / ...)
$user->can('posts.update.own', $post);
$user->explain('invoice.approve', $invoice); // includes condition results
```

### Multi-tenancy & scopes

Enable in config: `permission.teams.enabled = true`.

The package does **not** own your tenant models. Bind them as scopes (or use the optional `tenants` table).

```php
use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Scopes\Scope;

$orgA = Organization::find(1);
$orgB = Organization::find(2);

$user->assignRole('editor', $orgA);  // tenant A
$user->assignRole('viewer', $orgB);  // tenant B

AuthorizationContext::tenant($orgA);
$user->can('posts.create');          // uses editor in A

AuthorizationContext::switch($orgB);
$user->can('posts.create');          // viewer in B — isolated

// Nested: org admin applies to workspace when inheritance is on
$orgScope = Scope::for($organization, 'organization');
$wsScope = Scope::for($workspace, 'workspace', $orgScope);
AuthorizationContext::scope($workspace);
```

Global roles (`assignRole('super-admin')` with no tenant) apply across tenants only when `permission.teams.global_roles.cross_tenant` is true.

### Temporary access

```php
$user->givePermissionTo('reports.export', expiresAt: now()->addDays(7));
$user->givePermissionTo(
    'reports.export',
    startsAt: now()->addDay(),
    expiresAt: now()->addWeek()
);
$user->assignRole('contractor', expiresAt: now()->addHours(4));

$user->can('reports.export'); // false after expiry — reason EXPIRED_PERMISSION
```

### Delegation

The delegator must currently hold the permission. Expired and revoked delegations never authorize. If the delegator later loses the permission, the delegatee loses it too (no privilege escalation).

```php
$delegation = $user->delegate(
    'invoice.approve',
    to: $manager,
    until: now()->addHours(4),
    reason: 'On leave',
    resource: $invoice, // optional: only this resource
);

$user->revokeDelegation($delegation);
```

### Audit, history, versioning

```php
// config/permission.php
'audit' => [
    'enabled' => true,    // assignment + delegation + policy events
    'decisions' => false, // also log every can() — off by default
],

$permission->history();       // versions + related audits
$permission->rollbackTo(1);   // restore snapshot v1
```

Audit rows are append-only (no soft deletes).

---

## Roles & permissions API

### Roles

```php
$role = Role::findOrCreate('admin');

$role->givePermissionTo('posts.create', 'posts.delete');
$role->revokePermissionTo('posts.delete');
$role->syncPermissions(['posts.view', 'posts.create']);
$role->hasPermissionTo('posts.view');
```

### Users (`HasAuthorization`)

```php
$user->assignRole('editor');
$user->removeRole('editor');
$user->syncRoles('editor', 'author');

$user->hasRole('editor');
$user->hasAnyRole('editor', 'admin');
$user->hasAllRoles('editor', 'author');

$user->givePermissionTo('reports.export');
$user->givePermissionTo('reports.export', expiresAt: now()->addDays(7));
$user->revokePermissionTo('reports.export');
$user->syncPermissions(['reports.export']);
$user->delegate('invoice.approve', to: $manager, until: now()->addHours(4));

$user->can('posts.update');
$user->canAny(['posts.view', 'posts.create']);
$user->canAll(['posts.view', 'posts.create']);

$user->getRoleNames();
$user->getPermissionNames();

// Full access dump (roles + effective permissions + totals)
$export = $user->exportAccess();
$user->exportAccessJson();
```

### User access export (kill feature)

Export everything a user can do — assigned roles, direct permissions, wildcard-expanded effective permissions, grouped by resource/group, with totals.

```php
$export = $user->exportAccess();

$export['totals'];
// roles, direct_permissions, assigned_permissions, effective_permissions, groups, resources

$export['roles'];                 // each role + its permissions
$export['direct_permissions'];
$export['effective_permissions']; // name => source / via / group / resource
$export['by_group'];
$export['by_resource'];
```

```bash
php artisan permission:export {userId} --type=App\\Models\\User --format=table
php artisan permission:export 42 --format=json --path=storage/app/user-42-access.json
```

### Decisions

```php
$decision = $user->authorizeFor('invoice.approve', $invoice);

$decision->allowed(); // bool
$decision->denied();  // bool
$decision->reason;    // e.g. PERMISSION_MISSING
$decision->source;    // e.g. engine | fake | role | direct
$decision->toArray();

$user->explain('invoice.approve', $invoice); // array form of Decision
```

#### Denial / allow reasons

| Constant | Meaning |
|----------|---------|
| `ALLOWED` | Access granted |
| `PERMISSION_MISSING` | No matching allow |
| `ROLE_MISSING` | Required role not present |
| `EXPLICIT_DENY` | Explicit deny rule / fake deny |
| `EXPIRED_PERMISSION` | Time-bound access expired |
| `TENANT_MISMATCH` | Wrong tenant context |
| `SCOPE_MISMATCH` | Outside allowed scope |
| `RESOURCE_DENIED` | Resource check failed |
| `CONDITION_FAILED` | ABAC condition failed |
| `POLICY_DENIED` | Laravel policy denied |
| `DELEGATION_EXPIRED` | Delegated access expired |
| `CONTEXT_MISSING` | Unsafe / incomplete context (fail closed) |

---

## Middleware

Registered aliases: `permission`, `role`.

```php
Route::post('/posts', [PostController::class, 'store'])
    ->middleware('permission:posts.create');

Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');

// Multiple values (pipe-separated)
Route::middleware('permission:posts.create|posts.update')->group(...);
Route::middleware('role:admin|editor')->group(...);
```

Logic defaults (config):

```php
'middleware' => [
    'permission_logic' => 'or', // or 'and'
    'role_logic' => 'or',
],
```

---

## Blade

```blade
@can('posts.create')
    <a href="{{ route('posts.create') }}">New post</a>
@endcan

@role('admin')
    <p>Admin panel</p>
@endrole

@canall(['posts.view', 'posts.create'])
    <p>Full author tools</p>
@endcanall
```

Frontend checks are **UI-only**. Always enforce on the server.

### Vue

Publish helpers (`php artisan vendor:publish --tag=libinkk-permission-frontend`) or import from the package:

```js
import { createPermissionPlugin, usePermission } from 'vendor/libinkk-permission/vue';

app.use(createPermissionPlugin(window.__LIBINKK_PERMISSION__));
```

```vue
<button v-if="$can('posts.create')">Create Post</button>
```

```js
const { can, canAny, canAll, hasRole } = usePermission();
```

### React

```jsx
import { PermissionProvider, usePermission, Can, CanAny, CanAll } from 'vendor/libinkk-permission/react';

<PermissionProvider value={window.__LIBINKK_PERMISSION__}>
    <Can permission="posts.create"><CreatePostButton /></Can>
    <CanAny permissions={['posts.create', 'posts.update']}><PostActions /></CanAny>
    <CanAll permissions={['posts.view', 'posts.create']}><AdvancedEditor /></CanAll>
</PermissionProvider>
```

```jsx
const { can, canAny, canAll, hasRole } = usePermission();
```

### Authorization payload & API

Off by default. Enable `permission.frontend.enabled`.

```php
permission_payload(); // current user — roles, permissions, denials, scopes, resources
```

```blade
@permissionPayload
{{-- window.__LIBINKK_PERMISSION__ = {...} --}}
```

Optional HTTP endpoints (authenticated):

```http
GET /api/authorization
GET /api/users/{user}/access
GET /api/permissions/matrix
```

`/api/users/{user}/access` is self-only unless `frontend.access_user_permission` is set and the viewer can that ability.

Share into Blade / Inertia with `Libinkk\Permission\Frontend\ShareAuthorizationState` when `frontend.share` is true.

These payloads are **not** a security boundary.

---

## Discovery & PHP attributes

```php
use Libinkk\Permission\Attributes\Permission;

class PostController
{
    #[Permission('posts.publish', description: 'Publish blog posts', group: 'Posts')]
    public function publish()
    {
        //
    }
}
```

```bash
php artisan permission:discover
php artisan permission:discover --path=app/Actions --json
php artisan permission:sync          # discover + create missing DB rows
php artisan permission:sync --dry-run
```

Configure extra scan paths in `config/permission.php` → `discovery.paths`.

---

## Artisan commands

| Command | Purpose |
|---------|---------|
| `permission:install` | Publish config (+ optional migrate / `--frontend`) |
| `permission:resource` | Create resource permissions |
| `permission:discover` | Scan `#[Permission]` attributes |
| `permission:sync` | Persist discovered permissions |
| `permission:validate` | Integrity checks (duplicates, orphans, conflicts) |
| `permission:doctor` | Health report for the authorization system |
| `permission:cache` | Warm registry / role permission caches |
| `permission:cache:clear` | Invalidate authorization caches |
| `permission:export` | Export a user's total roles & permissions |

---

## Caching

Lookup order:

```text
L1 request memory → L2 app cache store → database
```

Prefix: `libinkk:permission:v1` (configurable).

Default TTLs:

| Data | TTL (seconds) |
|------|----------------|
| Permissions / roles | `86400` |
| User roles / permissions | `3600` |
| Scopes | `1800` |
| Decisions | `300` |

User and role mutations flush the relevant cache entries. On Laravel Octane, request cache is flushed per request/task/tick.

Configure via `config/permission.php` → `cache`.

---

## Configuration

Key options in `config/permission.php`:

```php
return [
    'enabled' => true,
    'default_guard' => 'web',

    'models' => [
        'user' => env('AUTH_MODEL', App\Models\User::class),
        'role' => Libinkk\Permission\Roles\Role::class,
        'permission' => Libinkk\Permission\Permissions\Permission::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        'user_roles' => 'user_roles',
        'user_permissions' => 'user_permissions',
    ],

    'database' => [
        'primary_key' => 'bigint', // bigint | uuid | ulid
        'user_key' => 'bigint',
    ],

    'cache' => [
        'enabled' => true,
        'prefix' => 'libinkk:permission:v1',
        // ...
    ],

    'teams' => ['enabled' => false],
    'hierarchy' => ['enabled' => false],
    'deny' => ['enabled' => false],
    'delegation' => ['enabled' => true],
    'versioning' => ['enabled' => true],
    'audit' => ['enabled' => false, 'decisions' => false],
    'frontend' => ['enabled' => false, 'routes' => true, 'prefix' => 'api'],
    'filament' => ['enabled' => false],
];
```

Set primary keys **before** migrating if you use UUID/ULID.

---

## Database tables

Core tables created by the package migration:

| Table | Purpose |
|-------|---------|
| `roles` | Role definitions |
| `permissions` | Permission definitions (`resource` / `action` / risk metadata) |
| `role_permissions` | Role ↔ permission (`effect`: allow/deny) |
| `user_roles` | Polymorphic user ↔ role (optional scope + expiry columns) |
| `user_permissions` | Polymorphic user ↔ permission (effect + scope + expiry) |
| `role_inheritances` | Parent role inherits child role permissions |
| `permission_conditions` | Named/typed ABAC conditions per permission |
| `permission_condition_values` | Structured values for conditions |
| `scopes` | Hierarchical authorization boundaries |
| `role_scopes` / `permission_scopes` / `user_scopes` | Scope membership |
| `tenants` / `tenant_users` | Optional package-owned tenants (off by default) |
| `permission_delegations` | Temporary delegated access (`pending` / `active` / `expired` / `revoked`) |
| `permission_versions` | Permission definition snapshots for history and rollback |
| `authorization_audits` | Append-only authorization and assignment history |

Soft deletes apply to roles and permissions only — never to audit rows.

---

## Events

| Event | When |
|-------|------|
| `RoleCreated` / `RoleUpdated` / `RoleDeleted` | Role lifecycle |
| `PermissionCreated` / `PermissionUpdated` / `PermissionDeleted` | Permission lifecycle |
| `RoleAssigned` / `RoleRemoved` | User role changes |
| `PermissionGranted` / `PermissionRevoked` | User direct permission changes |
| `AuthorizationAllowed` / `AuthorizationDenied` | Decision outcomes (when emitted by the engine path) |
| `DelegationCreated` / `DelegationRevoked` | Delegation lifecycle |
| `PolicyChanged` | Permission definition snapshot / rollback |

---

## Testing

### Fake permissions

```php
use Libinkk\Permission\Permissions\Permission;

Permission::fake();
Permission::allow('posts.create');
Permission::deny('posts.delete');

$this->assertTrue($user->can('posts.create'));
$this->assertFalse($user->can('posts.delete'));
```

### Assertions

```php
use Libinkk\Permission\Testing\InteractsWithAuthorization;

class ExampleTest extends TestCase
{
    use InteractsWithAuthorization;

    public function test_editor_can_update_posts(): void
    {
        $user->assignRole('editor');

        $this->assertCan($user, 'posts.update');
        $this->assertCannot($user, 'posts.delete');
    }
}
```

Run the package test suite:

```bash
composer test
# or
vendor/bin/phpunit
```

---

## Security principles

1. **Fail closed** — if authorization cannot be determined safely, return DENY.
2. **Database is source of truth** — cache is a performance layer only.
3. **Never trust Blade / Vue / React / Filament** for enforcement.
4. **No privilege escalation** via cache, fakes in production, or client payloads.
5. **Tenant isolation** (when teams/tenants are enabled) must hold in queries, cache keys, and decisions.

---

## Package layout

```text
src/
├── Attributes/      PHP #[Permission] attribute
├── Authorization/   Engine, Context, Decision, Precedence, ExpirationChecker, UserAccessExporter
├── Roles/           Role, RoleManager, RoleHierarchy
├── Scopes/          Scope, ScopeResolver, ScopeHierarchy, Tenant
├── Permissions/     Permission, versions, history, Manager, Registry, Resolver
├── Conditions/      Condition, ConditionRegistry, ConditionResolver, OwnershipChecker
├── Delegation/      Delegation, DelegationManager
├── Audit/           AuthorizationAudit, AuditLogger
├── Frontend/        Payload, matrix, API, Inertia/Blade share
├── Discovery/       AttributeScanner, PermissionDiscovery
├── Cache/           PermissionCache, DecisionCache, PermissionFake
├── Commands/        Artisan DX commands
├── Concerns/        HasAuthorization
├── Contracts/       Engine, repositories, cache, AuditLogger
├── Events/
├── Middleware/
├── Providers/
├── Repositories/
├── Support/         WildcardMatcher, PermissionValidator, PermissionDoctor
└── Testing/
```

Optional UI package (planned): `libinkk/permission-filament`.

---

## Ecosystem

Designed to integrate with the Libinkk stack (OneAuth, Modular, API Starter) without replacing your auth or tenant models.

- Homepage: [https://www.libinkk.in](https://www.libinkk.in)
- Support: libinkk1999@gmail.com

---

## License

MIT © [Libin K K](https://www.libinkk.in)
