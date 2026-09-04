# v1 Security Certificate

| Field | Value |
|--------|--------|
| **ID** | `LPK-SEC-2026-0905-V1` |
| **Subject** | `libinkk/permission` |
| **Scope** | v0.1–v0.7 authorization engine (RBAC, ABAC, tenants, delegation, audit, frontend, Filament adapter) |
| **Score** | **7.4 / 10** |
| **Disposition** | **CONDITIONAL PASS** |
| **Issued** | 5 September 2026 |
| **Evidence** | 135 PHPUnit tests / 376 assertions (local SQLite) |
| **Issuer** | Internal engineering assessment (not an accredited CA) |

This is an **internal scorecard**, not ISO, SOC 2, or third-party pentest attestation. Reproduce with:

```bash
vendor/bin/phpunit --no-coverage
```

## Conditional production use

The PHP authorization engine may be used in production if the host app:

- Treats Blade, Vue, React, and Filament as **UI only** — every write path is re-checked by Laravel → this package → ALLOW / DENY
- Pins a package commit
- Enables tenant context on every request when teams are on
- Leaves frontend APIs and decision audit off unless needed

Independent CI, pentest, and v1.0 ops docs are still outstanding.

## Domain scores

Weighted mean **7.4 / 10**.

| Domain | Score | Weight | Notes |
|--------|------:|-------:|-------|
| Fail-closed / default deny | 9.0 | 15% | Blank names, exceptions, missing context deny |
| Privilege-escalation controls | 8.5 | 15% | Deny beats wildcard / role / delegation; `Permission::fake()` blocked in production |
| Tenant / scope isolation | 8.0 | 12% | Mismatch + `require_context` tested; no live multi-DB |
| Delegation & time-bound access | 8.5 | 10% | No self-delegate, no re-delegate, only delegator revokes |
| Input / injection resistance | 8.0 | 8% | Bound queries; injection-style names do not match |
| Frontend trust boundary | 8.0 | 8% | Payload UI-only; guest empty; access is self-only |
| Cache integrity | 7.5 | 8% | No cross-user leak; no stampede / Octane proof |
| Audit tamper resistance | 7.5 | 7% | `delete` / `update` / `forceDelete` rejected; audit off by default |
| Independent verification / CI | 4.0 | 7% | Local PHPUnit only; no matrix, SAST, or pentest |
| System-record protection | 8.0 | 5% | `is_system` delete / unprotect throws |
| Operational maturity | 5.0 | 5% | No `SECURITY.md` reporting policy, upgrade guide, or scheduled expire job |

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

**Open (score deductions)**

- No GitHub Actions matrix for Laravel 10–13
- `POLICY_DENIED` is defined but unused (policies are not an engine step)
- No Octane isolation, UUID / ULID, or load tests
- Vue / React helpers are not executed in PHPUnit
- Filament / Inertia optional paths are not live-tested
- No third-party pentest or upgrade guide
- `Delegation::expireStale()` has no scheduler (timestamps still deny)

## How to read this score

| Band | Meaning |
|------|---------|
| 9.0–10 | Independent audit + CI matrix + ops docs — not this package yet |
| 7.0–8.9 | Engine design sound; verified in-repo; production with constraints |
| 5.0–6.9 | Core works but material control or evidence gaps |
| Below 5 | Do not ship as an authorization boundary |

---

`LPK-SEC-2026-0905-V1` · `libinkk/permission` · 5 September 2026
