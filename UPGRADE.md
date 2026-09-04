# Upgrade guide

## 0.x → 1.0.0

1.0 is a **documentation and release** milestone. The engine, tables, and public APIs from v0.1–v0.9 are unchanged. Pin a tag or commit and run the test suite in the host app.

### Before you upgrade

```bash
php artisan permission:doctor --json
php artisan permission:validate
php artisan permission:cache:clear
```

Take a database backup. Soft-deleted roles/permissions stay; **never** soft-delete `authorization_audits`.

### Composer

```bash
composer require libinkk/permission:^1.0
php artisan vendor:publish --tag=libinkk-permission-config --force
php artisan migrate
```

Review the published `config/permission.php`. New keys since early 0.x:

| Key | Default | Notes |
|-----|---------|--------|
| `cache.redis.enabled` | `false` | Opt-in L3 Redis |
| `debug.enabled` | `false` | Explain API + Telescope/DebugBar |
| `frontend.enabled` | `false` | UI payload + optional HTTP routes |
| `filament.enabled` | `false` | Filament adapter |
| `audit.enabled` | `false` | Assignment audit |
| `teams.enabled` | `false` | Multi-tenancy |
| `hierarchy.enabled` | `true` | Role inheritance |
| `deny.enabled` | `true` | Explicit deny |

### Breaking expectations (not schema)

- **Frontend is not a grant.** Blade / Vue / React / Filament hide UI only.
- **`Permission::fake()`** is blocked in production unless `permission.testing.allow_fake` is true.
- **Only the delegator** can revoke a delegation.
- **System roles/permissions** (`is_system`) cannot be deleted or unprotected.
- **Policies** are not an engine step. `POLICY_DENIED` exists for host use; `$user->can()` for dotted names is resolved by this package.

### UUID / ULID

Set `permission.database.primary_key` **before** the first migrate. Changing key type on an existing database is not an automatic migration — export, recreate, re-import.

### After upgrade

```bash
php artisan permission:cache
php artisan permission:doctor
vendor/bin/phpunit
```

Enable `teams`, `frontend`, `debug`, `audit`, and `filament` only when the host app needs them.

See [docs/deployment.md](docs/deployment.md) for production settings.
