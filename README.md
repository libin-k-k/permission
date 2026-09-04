# libinkk/permission

**v1.0** — production-grade Laravel authorization engine.

RBAC + ABAC + tenants + temporary access + delegation + explainable decisions + layered cache.

> Authorization that explains itself.

Most permission packages answer: *Does this user have this permission?*

This package answers:

> Is this user allowed to perform this action on this resource, in the current scope, under the current conditions — and if not, why?

```php
$user->can('invoice.approve', $invoice);
$user->explain('invoice.approve', $invoice);
```

This is **not** a thin roles CRUD helper. Blade, Vue, React, and Filament are **UI only**. Every write path must go Laravel → this package → ALLOW / DENY.

| Doc | Purpose |
|-----|---------|
| This README | Tutorials and API |
| [UPGRADE.md](UPGRADE.md) | 0.x → 1.0 |
| [SECURITY.md](SECURITY.md) | How to report issues |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [docs/deployment.md](docs/deployment.md) | Production checklist |
| [docs/benchmarks.md](docs/benchmarks.md) | Cache / query notes |

---

## Contents

1. [Compatibility](#compatibility)
2. [Installation](#installation)
3. [Tutorial 1 — Blog in 15 minutes](#tutorial-1--blog-in-15-minutes)
4. [Tutorial 2 — Invoice approval (ABAC + delegation)](#tutorial-2--invoice-approval-abac--delegation)
5. [Tutorial 3 — Multi-tenant SaaS](#tutorial-3--multi-tenant-saas)
6. [Permission naming](#permission-naming)
7. [Roles and users](#roles-and-users)
8. [Wildcards, hierarchy, deny](#wildcards-hierarchy-deny)
9. [Conditions, ownership, policies](#conditions-ownership-policies)
10. [Guards](#guards)
11. [Temporary access and delegation](#temporary-access-and-delegation)
12. [Audit, history, versioning](#audit-history-versioning)
13. [Middleware](#middleware)
14. [Blade](#blade)
15. [Vue](#vue)
16. [React](#react)
17. [Authorization API](#authorization-api)
18. [Filament](#filament)
19. [Discovery](#discovery)
20. [Debugger and Artisan](#debugger-and-artisan)
21. [Bulk, cache, performance](#bulk-cache-performance)
22. [Testing](#testing)
23. [Security](#security)
24. [Troubleshooting](#troubleshooting)
25. [Architecture](#architecture)

---

## Compatibility

| Laravel | PHP |
|---------|-----|
| 10 | 8.1 – 8.3 |
| 11 | 8.2 – 8.4 |
| 12 | 8.2 – 8.4 |
| 13 | 8.3 – 8.4 |

Package version: `Libinkk\Permission\Version::VERSION` → **1.0.0**.

CI: [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

---

## Installation

```bash
composer require libinkk/permission:^1.0
php artisan permission:install --migrate
```

Or publish by hand:

```bash
php artisan vendor:publish --tag=libinkk-permission-config
php artisan migrate
```

Set `permission.database.primary_key` to `bigint` (default), `uuid`, or `ulid` **before** the first migrate.

Add the trait. The package does **not** own `users`. Assignments are `user_type` / `user_id`.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Libinkk\Permission\Concerns\HasAuthorization;

class User extends Authenticatable
{
    use HasAuthorization;
}
```

```bash
php artisan permission:doctor
```

---

## Tutorial 1 — Blog in 15 minutes

Goal: editors can write posts, admins can delete, guests cannot.

### 1. Define permissions

```php
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;

Permission::crud('posts'); // posts.view / create / update / delete
Permission::define('posts.publish', ['group' => 'Posts']);
```

Or:

```bash
php artisan permission:resource posts --actions=view,create,update,delete,publish --group=Posts
```

### 2. Create roles

```php
Role::findOrCreate('viewer')->givePermissionTo('posts.view');
Role::findOrCreate('editor')->givePermissionTo('posts.view', 'posts.create', 'posts.update');
Role::findOrCreate('admin')->givePermissionTo('posts.*');
```

### 3. Assign and check

```php
$editor = User::find(1);
$editor->assignRole('editor');

$editor->can('posts.update'); // true
$editor->can('posts.delete'); // false
$editor->explain('posts.delete');
// ['allowed' => false, 'reason' => 'PERMISSION_MISSING', ...]
```

### 4. Protect routes

```php
Route::post('/posts', [PostController::class, 'store'])
    ->middleware(['auth', 'permission:posts.create']);

Route::delete('/posts/{post}', [PostController::class, 'destroy'])
    ->middleware(['auth', 'permission:posts.delete']);
```

### 5. Hide UI (not a grant)

```blade
@can('posts.create')
    <a href="{{ route('posts.create') }}">New post</a>
@endcan
```

### 6. Still check in the controller

```php
public function store(Request $request)
{
    $this->authorize('posts.create');

    Post::query()->create($request->validated());
}
```

`@can` only hides the link. `authorize()` / middleware is the security boundary.

---

## Tutorial 2 — Invoice approval (ABAC + delegation)

Goal: finance managers approve invoices under their limit. A manager on leave can delegate.

```php
use Libinkk\Permission\Conditions\Condition;
use Libinkk\Permission\Permissions\Permission;

Permission::define('invoice.approve', ['group' => 'Finance', 'is_dangerous' => true]);

Condition::define('within_approval_limit', function ($user, $invoice) {
    return (int) $invoice->amount <= (int) $user->approval_limit;
});

Permission::define('invoice.approve')->when('within_approval_limit');
```

```php
$manager = User::find(10);
$manager->givePermissionTo('invoice.approve');
$manager->approval_limit = 50_000;
$manager->save();

$ok = Invoice::query()->create(['amount' => 20_000]);
$over = Invoice::query()->create(['amount' => 125_000]);

$manager->can('invoice.approve', $ok);   // true
$manager->can('invoice.approve', $over); // false — CONDITION_FAILED

$decision = $manager->authorizeFor('invoice.approve', $over);
$decision->reason;      // CONDITION_FAILED
$decision->conditions;  // ['within_approval_limit' => false]
```

Delegate for four hours (delegator must already hold the permission):

```php
$cover = User::find(11);

$delegation = $manager->delegate(
    'invoice.approve',
    to: $cover,
    until: now()->addHours(4),
    reason: 'On leave',
    resource: $ok, // optional: only this invoice
);

$cover->can('invoice.approve', $ok); // true until expiry or revoke
$manager->revokeDelegation($delegation);
```

Only the **delegator** can revoke. Self-delegation is rejected. If the manager later loses `invoice.approve`, the cover user loses it too.

Explain in the terminal:

```bash
php artisan permission:explain 10 invoice.approve
```

---

## Tutorial 3 — Multi-tenant SaaS

Goal: the same user is an editor in Org A and a viewer in Org B.

```php
// config/permission.php
'teams' => [
    'enabled' => true,
    'require_context' => true, // deny if no tenant is set
],
```

```php
use Libinkk\Permission\Authorization\AuthorizationContext;

$user->assignRole('editor', $orgA);
$user->assignRole('viewer', $orgB);

AuthorizationContext::tenant($orgA);
$user->can('posts.create'); // editor in A

AuthorizationContext::switch($orgB);
$user->can('posts.create'); // isolated — viewer in B
```

Nested scopes (organization → workspace):

```php
use Libinkk\Permission\Scopes\Scope;

$orgScope = Scope::for($organization, 'organization');
$wsScope = Scope::for($workspace, 'workspace', $orgScope);

AuthorizationContext::scope($workspace);
```

On every HTTP request (middleware):

```php
public function handle($request, Closure $next)
{
    if ($tenant = $request->route('organization')) {
        AuthorizationContext::tenant($tenant);
    }

    return $next($request);
}
```

Queue jobs must set the tenant again. Global roles (`assignRole('super-admin')` with no tenant) cross tenants only when `teams.global_roles.cross_tenant` is true.

---

## Permission naming

Format: `resource.action`

```text
posts.view   posts.create   posts.update   posts.delete   posts.publish
invoices.approve
reports.export
```

Names that contain `.` are managed by this package through `Gate::before`.

```php
Permission::defineResource('posts', ['view', 'create', 'update', 'delete', 'publish'], [
    'group' => 'Posts',
]);

Permission::crud('invoices'); // view/create/update/delete

Permission::inGroup('Posts');
```

---

## Roles and users

```php
$role = Role::findOrCreate('admin');
$role->givePermissionTo('posts.create', 'posts.delete');
$role->revokePermissionTo('posts.delete');
$role->syncPermissions(['posts.view', 'posts.create']);
$role->hasPermissionTo('posts.view');

$user->assignRole('editor');
$user->removeRole('editor');
$user->syncRoles('editor', 'author');
$user->hasRole('editor');
$user->hasAnyRole('editor', 'admin');
$user->hasAllRoles('editor', 'author');

$user->givePermissionTo('reports.export');
$user->revokePermissionTo('reports.export');
$user->getRoleNames();
$user->getPermissionNames();

$export = $user->exportAccess();
$export['totals'];
$export['effective_permissions'];
```

```bash
php artisan permission:export 42 --type=App\\Models\\User --format=table
```

System rows (`is_system = true`) cannot be deleted or unprotected.

---

## Wildcards, hierarchy, deny

```php
$role->givePermissionTo('posts.*');
$user->can('posts.view');   // true
$user->can('posts.delete'); // true

$user->givePermissionTo('posts.view.*');
$user->can('posts.view.own'); // true
```

Exact matches beat wildcards. A wildcard does not cross resources (`posts.*` is not `invoices.view`).

```php
Role::inherit('admin', 'manager');   // admin inherits manager
Role::inherit('manager', 'editor');
Role::uninherit('admin', 'manager');
```

Circular inheritance throws and is reported by `permission:validate`. Disable with `hierarchy.enabled = false`.

```php
$user->denyPermissionTo('posts.delete'); // beats role allow
$role->denyPermissionTo('posts.publish');
```

Default precedence:

```text
explicit deny → explicit allow → role deny → role allow
  → inherited deny → inherited allow → default deny
```

---

## Conditions, ownership, policies

```php
Permission::define('posts.update.own')
    ->when(fn ($user, $post) => $post->author_id === $user->id);

$user->can('posts.update.own', $post);
```

`.own` also auto-checks `author_id`, `user_id`, `owner_id`, or `permission.ownership.attribute`.

Thrown or unknown conditions **deny**.

Laravel policies are **not** an engine step. Dotted abilities are resolved here. Use a host policy for non-dotted abilities, or call both yourself. `POLICY_DENIED` is reserved for that host wiring.

---

## Guards

Roles and permissions have `guard_name` (default `web`). A `web` permission does not authorize an `api` user.

```php
Role::findOrCreate('editor', 'api');
Permission::findOrCreate('posts.view', 'api');
```

---

## Temporary access and delegation

```php
$user->givePermissionTo('reports.export', expiresAt: now()->addDays(7));
$user->givePermissionTo('reports.export', startsAt: now()->addDay(), expiresAt: now()->addWeek());
$user->assignRole('contractor', expiresAt: now()->addHours(4));
```

After expiry, `can()` is false with `EXPIRED_PERMISSION` or `DELEGATION_EXPIRED`.

Delegation rules: delegator must hold the permission; no self-delegate; no re-delegate; only the delegator revokes; expired/revoked never authorize.

---

## Audit, history, versioning

```php
'audit' => [
    'enabled' => true,
    'decisions' => false, // every can() — leave off in production
],
```

```php
$permission->history();
$permission->rollbackTo(1);
```

Audit rows are append-only (no update / delete / soft delete).

---

## Middleware

Aliases: `permission`, `role`.

```php
Route::middleware('permission:posts.create')->group(...);
Route::middleware('permission:posts.create|posts.update')->group(...);
Route::middleware('role:admin|editor')->group(...);
```

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
    <p>Admin</p>
@endrole

@canall(['posts.view', 'posts.create'])
    <p>Author tools</p>
@endcanall

@permissionPayload
{{-- window.__LIBINKK_PERMISSION__ = {...} --}}
```

Frontend checks are **UI-only**.

---

## Vue

```bash
php artisan vendor:publish --tag=libinkk-permission-frontend
```

```js
import { createPermissionPlugin, usePermission } from '@/js/vendor/libinkk-permission/vue';

app.use(createPermissionPlugin(window.__LIBINKK_PERMISSION__));
```

```vue
<button v-if="$can('posts.create')">Create</button>
<button v-if="$canAny(['posts.create', 'posts.update'])">Edit</button>
```

```js
const { can, canAny, canAll, hasRole } = usePermission();
```

---

## React

```jsx
import { PermissionProvider, usePermission, Can, CanAny, CanAll } from '@/js/vendor/libinkk-permission/react';

<PermissionProvider value={window.__LIBINKK_PERMISSION__}>
    <Can permission="posts.create"><CreatePostButton /></Can>
    <CanAny permissions={['posts.create', 'posts.update']}><PostActions /></CanAny>
    <CanAll permissions={['posts.view', 'posts.create']}><AdvancedEditor /></CanAll>
</PermissionProvider>
```

```jsx
const { can, canAny, canAll, hasRole } = usePermission();
```

---

## Authorization API

Off until `permission.frontend.enabled` is true.

```php
permission_payload(); // current user — UI only
```

```http
GET /api/authorization
GET /api/users/{user}/access
GET /api/permissions/matrix
```

`/access` is **self-only** unless `frontend.access_user_permission` is set (for example `users.access`) and the viewer `can()` that ability.

---

## Filament

Not a Composer requirement. Set `filament.enabled` when Filament is installed.

```php
use Libinkk\Permission\Filament\AuthorizesFilamentResource;
use Libinkk\Permission\Filament\FilamentAuthorization;

class PostResource extends Resource
{
    use AuthorizesFilamentResource;

    protected static ?string $permissionResource = 'posts';
}

Action::make('approve')->visible(FilamentAuthorization::visible('invoice.approve'));
BulkAction::make('delete')->authorize(FilamentAuthorization::bulkCallback('posts.delete'));
```

```php
$panel->middleware(\Libinkk\Permission\Filament\PermissionFilamentPlugin::middleware());
```

Admin CRUD / matrix UI is a separate package (`libinkk/permission-filament`). Use `FilamentAuthorization::debug()` on a custom page for the debugger report.

---

## Discovery

```php
use Libinkk\Permission\Attributes\Permission;

class PostController
{
    #[Permission('posts.publish', description: 'Publish posts', group: 'Posts')]
    public function publish() {}
}
```

```bash
php artisan permission:discover --path=app/Http/Controllers
php artisan permission:sync
php artisan permission:sync --dry-run
```

---

## Debugger and Artisan

```php
$report = $user->debugAuthorization('invoice.approve', $invoice);
$report['final'];  // ALLOWED | DENIED
$report['text'];
$report['checks'];
```

```bash
php artisan permission:install
php artisan permission:resource posts
php artisan permission:discover
php artisan permission:sync
php artisan permission:validate
php artisan permission:doctor
php artisan permission:graph --json
php artisan permission:unused
php artisan permission:explain {userId} invoice.approve
php artisan permission:cache
php artisan permission:cache:clear
php artisan permission:export {userId}
```

`GET /api/authorization/explain?permission=…` is off until `debug.enabled`. Current user only. Not a grant.

---

## Bulk, cache, performance

```php
$user->preloadAuthorization();
$user->permissionsFor('posts');
// ['view' => true, 'create' => true, 'update' => false, ...]

$decisions = $user->authorizeMany('posts.update', $posts);
```

```text
L1 request memory → L2 app cache → optional L3 Redis → database
```

Prefix: `libinkk:permission:v1`. Redis L3 is off until `cache.redis.enabled` is true.

| Data | Default TTL |
|------|-------------|
| Permissions / roles | 86400 |
| User roles / permissions | 3600 |
| Scopes | 1800 |
| Decisions | 300 |

Mutations invalidate after DB commit. Octane and queue workers flush request state each cycle.

Details: [docs/benchmarks.md](docs/benchmarks.md).

---

## Testing

```php
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Testing\InteractsWithAuthorization;

Permission::fake();
Permission::allow('posts.create');
Permission::deny('posts.delete');

$this->assertCan($user, 'posts.update');
$this->assertCannot($user, 'posts.delete', $post);
```

`Permission::fake()` is disabled in production unless `permission.testing.allow_fake` is true.

```bash
composer test
vendor/bin/phpunit --no-coverage
```

---

## Security

1. Fail closed.
2. Database is source of truth.
3. Never trust Blade / Vue / React / Filament for enforcement.
4. No privilege escalation via cache, fakes, or client payloads.
5. Tenant isolation when teams are on.

See [SECURITY.md](SECURITY.md). Internal scorecard: [SECURITY-CERTIFICATE.md](SECURITY-CERTIFICATE.md) (7.4 / 10, not a third-party cert).

Production settings: [docs/deployment.md](docs/deployment.md).

### Configuration (defaults)

```php
return [
    'enabled' => true,
    'default_guard' => 'web',
    'database' => [
        'primary_key' => 'bigint', // bigint | uuid | ulid
        'user_key' => 'bigint',
    ],
    'cache' => [
        'enabled' => true,
        'prefix' => 'libinkk:permission:v1',
        'redis' => ['enabled' => false, 'store' => 'redis'],
        'decision_cache' => ['enabled' => true],
    ],
    'teams' => ['enabled' => false, 'require_context' => false],
    'hierarchy' => ['enabled' => true],
    'deny' => ['enabled' => true],
    'delegation' => ['enabled' => true],
    'versioning' => ['enabled' => true],
    'audit' => ['enabled' => false, 'decisions' => false],
    'frontend' => ['enabled' => false],
    'debug' => ['enabled' => false],
    'filament' => ['enabled' => false],
    'testing' => ['allow_fake' => false],
];
```

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Always deny | `permission.enabled`, user trait, `permission:doctor` |
| Tenant deny | `AuthorizationContext::tenant()` on the request; `teams.require_context` |
| Stale allow after revoke | `permission:cache:clear`; confirm after-commit ran |
| Wildcard too wide | Wildcards do not cross resources; prefer exact + deny |
| Delegation not working | Delegator must `can()` the permission now; not expired; not self |
| Fake in production | Blocked unless `testing.allow_fake` |
| Explain 404 | `debug.enabled` is false (default) |
| Frontend 404 | `frontend.enabled` is false (default) |
| UUID attach failed | Upgrade to 1.0 pivot id generation; set key type before first migrate |

```bash
php artisan permission:doctor --json
php artisan permission:validate
php artisan permission:explain {id} posts.delete --json
```

---

## Architecture

```text
Request → Gate / middleware / $user->can()
        → AuthorizationEngine (fail closed)
        → roles, directs, hierarchy, deny, conditions, expiry, delegation
        → Decision { allowed, reason, source, checks }
        → L1 / L2 / L3 cache
        → Blade / Vue / React / Filament  (UI only)
```

### Tables

| Table | Purpose |
|-------|---------|
| `roles` / `permissions` | Definitions |
| `role_permissions` | Role ↔ permission (`allow` / `deny`) |
| `user_roles` / `user_permissions` | Polymorphic assignments + expiry |
| `role_inheritances` | Hierarchy |
| `permission_conditions` / `permission_condition_values` | ABAC |
| `scopes` / `*_scopes` | Hierarchical boundaries |
| `tenants` / `tenant_users` | Optional package tenants |
| `permission_delegations` | Delegated access |
| `permission_versions` | Snapshots |
| `authorization_audits` | Append-only log |

Soft deletes: roles and permissions only.

### Events

`RoleCreated` / `Updated` / `Deleted`, `PermissionCreated` / `Updated` / `Deleted`, `RoleAssigned` / `Removed`, `PermissionGranted` / `Revoked`, `AuthorizationAllowed` / `Denied`, `DelegationCreated` / `Revoked`, `PolicyChanged`.

### Roadmap

| Version | Status |
|---------|--------|
| v0.1 – v0.9 | Shipped in 1.0 |
| **v1.0** | Docs, upgrade guide, CI matrix, production guide ✅ |

### Package layout

```text
src/   Authorization, Roles, Permissions, Conditions, Scopes,
       Delegation, Audit, Frontend, Filament, Debug, Cache, Commands
```

---

## Ecosystem

Integrates with Libinkk OneAuth, Modular, and API Starter without replacing auth or tenant models.

- Homepage: [https://www.libinkk.in](https://www.libinkk.in)
- Support: libinkk1999@gmail.com
- License: MIT © [Libin K K](https://www.libinkk.in)
