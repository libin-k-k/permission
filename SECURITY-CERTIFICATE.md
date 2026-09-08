# v1.1 Security Certificate

| Field | Value |
|--------|--------|
| **ID** | `LPK-SEC-2026-0908-V1.1` |
| **Supersedes** | `LPK-SEC-2026-0905-V1` |
| **Subject** | `libinkk/permission` |
| **Scope** | v1.0 authorization engine (RBAC, ABAC, tenants, delegation, audit, frontend, Filament adapter, debug tools, cache layers) |
| **Score** | **8.0 / 10** |
| **Disposition** | **CONDITIONAL PASS** |
| **Issued** | 8 September 2026 |
| **Evidence** | 164 PHPUnit tests / 472 assertions; GitHub Actions 10/10 green (PHP 8.1–8.5 × Laravel 10–13) |
| **Issuer** | Internal engineering assessment (not an accredited CA) |

This is an **internal scorecard**, not ISO, SOC 2, or third-party pentest attestation. Reproduce with:

```bash
vendor/bin/phpunit --no-coverage
```

CI: [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

## Why this revision

`LPK-SEC-2026-0905-V1` scored **Independent verification / CI at 4.0** because only local PHPUnit existed. On 8 September 2026 the GitHub Actions matrix completed green, including PHP 8.5 / Laravel 12 and PHP 8.5 / Laravel 13. `SECURITY.md`, `UPGRADE.md`, and `docs/deployment.md` also exist. Those close documentation and CI evidence gaps. They do **not** replace a pentest or SAST.

## Conditional production use

The PHP authorization engine may be used in production if the host app:

- Treats Blade, Vue, React, and Filament as **UI only** — every write path is re-checked by Laravel → this package → ALLOW / DENY
- Pins a package commit
- Enables tenant context on every request when teams are on
- Leaves frontend APIs and decision audit off unless needed

Pentest and SAST are still outstanding.

## Domain scores

Weighted mean **8.0 / 10**.

| Domain | Score | Weight | Notes |
|--------|------:|-------:|-------|
| Fail-closed / default deny | 9.0 | 15% | Blank names, exceptions, missing context deny |
| Privilege-escalation controls | 8.5 | 15% | Deny beats wildcard / role / delegation; `Permission::fake()` blocked in production |
| Tenant / scope isolation | 8.0 | 12% | Mismatch + `require_context` tested; no live multi-DB |
| Delegation & time-bound access | 8.5 | 10% | No self-delegate, no re-delegate, only delegator revokes |
| Input / injection resistance | 8.0 | 8% | Bound queries; injection-style names do not match |
| Frontend trust boundary | 8.0 | 8% | Payload UI-only; guest empty; access is self-only |
| Cache integrity | 8.0 | 8% | No cross-user leak; Octane/queue flush tested; no published load-lab SLA |
| Audit tamper resistance | 7.5 | 7% | `delete` / `update` / `forceDelete` rejected; audit off by default |
| Independent verification / CI | 6.5 | 7% | GitHub Actions matrix green; still no SAST or pentest |
| System-record protection | 8.0 | 5% | `is_system` delete / unprotect throws |
| Operational maturity | 6.5 | 5% | `SECURITY.md`, `UPGRADE.md`, deployment guide; no scheduled `expireStale` job |

## Controls verified

**Pass**

- Fail closed on missing permission, blank / null-byte names, thrown conditions
- Explicit deny beats wildcard, role allow, and delegation
- Wildcard does not cross resources; `.own` without a resource denies
- Delegator must hold the permission; delegatee cannot revoke or re-delegate
- Inactive / soft-deleted permissions and inactive roles do not authorize
- Tenant mismatch and missing required context deny
- Child scope does not grant parent; guards are isolated
- Cache does not leak across users; grant invalidates stale deny
- Audit rows immutable; frontend payload is not a grant
- `Permission::fake()` disabled in production
- GitHub Actions matrix green: Laravel 10–13, PHP 8.1–8.5
- Octane / queue workers flush request cache, tenant, and impersonation
- UUID / ULID pivot keys set on attach

**Open (score deductions)**

- `POLICY_DENIED` is defined but unused (policies are not an engine step)
- No published load-lab SLA or cache-stampede study
- Vue / React helpers are not executed in PHPUnit
- Filament / Inertia optional paths are not live-tested
- No third-party pentest or SAST
- `Delegation::expireStale()` has no scheduler (timestamps still deny)

## How to read this score

| Band | Meaning |
|------|---------|
| 9.0–10 | Independent pentest / SAST plus CI and ops docs |
| 7.0–8.9 | Engine design sound; verified in-repo and CI; production with constraints |
| 5.0–6.9 | Core works but material control or evidence gaps |
| Below 5 | Do not ship as an authorization boundary |

---

`LPK-SEC-2026-0908-V1.1` · supersedes `LPK-SEC-2026-0905-V1` · `libinkk/permission` · 8 September 2026
