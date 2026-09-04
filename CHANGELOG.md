# Changelog

All notable changes to `libinkk/permission` are documented here.

## [1.0.0] — 2026-09-05

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

## [0.9.0] — 2026-09-05

Redis L3 opt-in, `authorizeMany` / `permissionsFor` / `preloadAuthorization`, Octane and queue flush, UUID/ULID pivot keys, cache metrics.

## [0.8.0] — 2026-09-05

Authorization debugger, `permission:graph`, `permission:unused`, `permission:explain`, optional Telescope / DebugBar hooks.

## [0.7.0] — 2026-09-04

Optional Filament resource/page/widget/relation/tenant adapter (no Filament Composer dependency).

## [0.6.0] — 2026-09-04

Vue / React helpers, frontend payload, optional authorization API.

## [0.5.0] — 2026-09-04

Temporary access, delegation, audit logs, permission versioning.

## [0.4.0] — 2026-09-03

Multi-tenancy, hierarchical scopes, authorization context.

## [0.3.0] — 2026-09-03

Conditions, ownership, role hierarchy, explicit deny, explainable decisions.

## [0.2.0] — 2026-09-02

Resources, groups, wildcards, Artisan DX, discovery, doctor, user export.

## [0.1.0] — 2026-09-01

Roles, permissions, Gate, middleware, Blade, layered cache.
