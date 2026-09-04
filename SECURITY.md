# Security

`libinkk/permission` is an authorization engine. Report vulnerabilities privately. Do not open a public GitHub issue for an exploitable grant.

## Report

Email **libinkk1999@gmail.com** with:

- Package version / commit
- Laravel and PHP versions
- Steps to reproduce a **grant** that should have been a deny
- Impact (cross-tenant leak, privilege escalation, cache bypass)

We will acknowledge the report and work a fix before any public disclosure.

## What we treat as a vulnerability

- A `can()` / Gate / middleware path that **allows** when it should deny
- Cross-tenant or cross-guard leakage
- Cache serving a stale allow after revoke
- Frontend / Filament payload treated as a server grant (if the engine honors it)
- Delegation or fake-permission privilege escalation
- Ability to delete or unprotect `is_system` records
- Mutation of `authorization_audits`

## What we do not treat as a vulnerability

- UI showing or hiding buttons (Blade, Vue, React, Filament)
- Disabled features (`frontend`, `debug`, `audit`) returning 404
- Host apps that skip `$user->can()` on write paths

## Hardening defaults

- Fail closed on missing context, blank names, thrown conditions
- Database is source of truth; cache failures do not grant
- `Permission::fake()` disabled in production
- Audit rows are append-only
- Debug and frontend APIs are off until enabled; explain is current-user only

Internal scorecard: [SECURITY-CERTIFICATE.md](SECURITY-CERTIFICATE.md) (`LPK-SEC-2026-0905-V1`, 7.4 / 10, conditional pass). That file is **not** a third-party certificate.
