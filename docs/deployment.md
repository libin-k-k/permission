# Production deployment

## Compatibility

| Laravel | PHP | Testbench (dev) |
|---------|-----|-----------------|
| 10 | 8.1–8.3 | 8.x |
| 11 | 8.2–8.4 | 9.x |
| 12 | 8.2–8.4 | 10.x |
| 13 | 8.3–8.4 | 11.x |

CI matrix: [`.github/workflows/tests.yml`](../.github/workflows/tests.yml).

## Install

```bash
composer require libinkk/permission:^1.0
php artisan permission:install --migrate
```

Add `HasAuthorization` to the authenticatable model. Do not replace your `users` table.

Set `permission.database.primary_key` (`bigint` / `uuid` / `ulid`) **before** migrate.

## Recommended production config

```php
'enabled' => true,
'cache' => [
    'enabled' => true,
    'store' => env('PERMISSION_CACHE_STORE', 'redis'),
    'redis' => ['enabled' => true, 'store' => 'redis'], // L3 only if L2 is not already redis
    'decision_cache' => ['enabled' => true],
    'lock' => ['enabled' => true],
],
'teams' => ['enabled' => true, 'require_context' => true], // only if you are multi-tenant
'frontend' => ['enabled' => false], // enable only if the SPA needs a payload
'debug' => ['enabled' => false],
'audit' => ['enabled' => true, 'decisions' => false],
'filament' => ['enabled' => false],
'testing' => ['allow_fake' => false],
```

Never enable `debug` or `Permission::fake()` in production.

## Workers

Octane, queue workers, and RoadRunner must not keep tenant or impersonation state. The package flushes on Octane request/task/tick and on `JobProcessing` / `JobProcessed` / `JobFailed` / `Looping`.

Jobs that authorize must set context themselves:

```php
AuthorizationContext::tenant($this->tenantId);
$user->can('invoices.approve', $invoice);
```

## Cache

```bash
php artisan permission:cache
php artisan permission:cache:clear
```

`cache:clear` drops **package** keys (generation bump + registry), not the entire Redis database.

After role/permission deploys, warm once, then rely on after-commit invalidation.

## Health

```bash
php artisan permission:doctor
php artisan permission:validate
php artisan permission:unused
```

Treat doctor **fail** as a deploy blocker. Warnings (unused permissions, stale delegation rows) should be reviewed.

## Enforcement rule

Every write path:

```text
Laravel request → $user->can() / Gate / middleware → this package → ALLOW | DENY
```

Blade, Vue, React, and Filament only hide controls.
