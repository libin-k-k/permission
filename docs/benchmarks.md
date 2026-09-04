# Performance notes

These are **engineering targets**, not contractual SLAs. Measure in the host app with production-like data.

## Lookup path

```text
L1 request memory   → sub-millisecond after the first check
L2 app cache store  → typically low milliseconds (Redis/array)
L3 Redis (opt-in)   → shared across workers when L2 is not Redis
Database            → indexed queries on miss
```

Prefix: `libinkk:permission:v1:`.

## What to preload

```php
$user->preloadAuthorization();
$user->permissionsFor('posts');
$user->authorizeMany('posts.update', $posts);
```

Do **not** warm every user on deploy. `php artisan permission:cache` warms the registry, role permissions, and hierarchy only.

## Query budget

After `preloadAuthorization()`, repeated `$user->can('posts.view')` should hit L1. `authorizeMany()` loads the permission map once, then evaluates each resource (ownership/conditions still run per record).

Resource-specific decisions and dynamic conditions stay request-scoped by default.

## Octane / queues

Request cache, `AuthorizationContext`, impersonation, metrics, and `Permission::fake()` reset per request and per job. Do not store the current tenant on a long-lived worker singleton.

## How we measure in this repo

The package test suite includes cache-hit, invalidation, UUID key, and bounded-query bulk tests (`tests/Unit/BulkAuthorizationTest.php`, `CacheLayerTest.php`). There is no published ops benchmark number from a dedicated load lab.

`php artisan permission:doctor` reports cache hit rate for the current process.
