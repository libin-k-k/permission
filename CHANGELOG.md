# Changelog

All notable changes to `libinkk/permission` are documented here.

## [1.1.1] - 2026-11-20

### Added

- PHP 8.6 on the Laravel 13 CI matrix (verified locally with Docker)
- README Laravel 13 compatibility range through PHP 8.6

### Fixed

- CI Composer installs for Laravel 10/11 blocked by Packagist security advisories (`audit.block-insecure` / `--no-security-blocking`)
- Synced `Version::VERSION` with the published tag line

## [1.1.0] - 2026-11-11

### Added

- `ROLE_MISSING` denial reason when a role grants the ability but the user lacks it
- `CannotDelegatePermissionException::redelegate()` for explicit re-delegation rejects
- DB triggers so `authorization_audits` cannot be updated/deleted (SQLite / MySQL / MariaDB / PostgreSQL)
- `cache.watch_db_mutations` to bump cache generation on raw permission-table writes
- Internet-known-issues regression tests

### Changed

- Audit Eloquent mutations throw `AuditImmutableException` (append-only)
- README accuracy pass (Gate scope, wildcards, Blade, Artisan flags, config snippet)

## [1.0.0] - 2026-09-05

Production release of the authorization engine (v0.1–v0.9 feature set + docs).

### Added

- Tutorial-style README covering installation through production
- [UPGRADE.md](UPGRADE.md) for 0.x → 1.0
- [SECURITY.md](SECURITY.md) vulnerability reporting
- [docs/deployment.md](docs/deployment.md) production checklist
- [docs/benchmarks.md](docs/benchmarks.md) performance notes
- GitHub Actions matrix for Laravel 10–13 / PHP 8.1–8.4
- `Libinkk\Permission\Version::VERSION` (`1.0.0`)

### Included from 0.x

- v0.1 RBAC, Gate, middleware, Blade, cache
- v0.2 Resources, wildcards, Artisan, discovery
- v0.3 ABAC, hierarchy, explicit deny, `explain()`
- v0.4 Tenants, scopes, `AuthorizationContext`
- v0.5 Temporary access, delegation, audit, versioning
- v0.6 Vue / React / authorization API
- v0.7 Optional Filament adapter
- v0.8 Debugger, graph, unused, CLI explain
- v0.9 Redis L3 opt-in, bulk APIs, Octane/queue flush, UUID/ULID

## [0.9.0] - 2026-09-05

Redis L3 opt-in, `authorizeMany` / `permissionsFor` / `preloadAuthorization`, Octane and queue flush, UUID/ULID pivot keys, cache metrics.

## [0.8.0] - 2026-09-05

Authorization debugger, `permission:graph`, `permission:unused`, `permission:explain`, optional Telescope / DebugBar hooks.

## [0.7.0] - 2026-09-04

Optional Filament resource/page/widget/relation/tenant adapter (no Filament Composer dependency).

## [0.6.0] - 2026-09-04

Vue / React helpers, frontend payload, optional authorization API.

## [0.5.0] - 2026-09-04

Temporary access, delegation, audit logs, permission versioning.

## [0.4.0] - 2026-09-03

Multi-tenancy, hierarchical scopes, authorization context.

## [0.3.0] - 2026-09-03

Conditions, ownership, role hierarchy, explicit deny, explainable decisions.

## [0.2.0] - 2026-09-02

Resources, groups, wildcards, Artisan DX, discovery, doctor, user export.

## [0.1.0] - 2026-09-01

Roles, permissions, Gate, middleware, Blade, layered cache.
