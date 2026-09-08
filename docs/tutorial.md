---
meta_title: "Laravel Authorization Engine Tutorial | libinkk/permission"
meta_description: "Why Laravel role packages are not enough. libinkk/permission decides if this user may act on this resource, in this tenant, and explains why. Full feature tutorial."
canonical: "https://www.libinkk.in/permission"
og_title: "Laravel Authorization Engine Tutorial | libinkk/permission"
og_description: "Goal, problem, and solution: compare Spatie, Bouncer, Laratrust, and Laravel policies with libinkk/permission 1.0. Real invoice, tenant, and HR examples."
og_url: "https://www.libinkk.in/permission"
og_type: "article"
og_site_name: "Libinkk"
og_image: "https://www.libinkk.in/images/permission-tutorial.png"
twitter_card: "summary_large_image"
twitter_title: "Laravel Authorization Engine Tutorial | libinkk/permission"
twitter_description: "RBAC + ABAC + tenants + delegation. Compare Laravel permission packages and learn every libinkk/permission feature with live examples."
robots: "index, follow, max-image-preview:large"
author: "Libin K K"
publisher: "Libinkk"
date_published: "2026-09-05"
date_modified: "2026-09-05"
locale: "en_IN"
package: "libinkk/permission"
version: "1.0.0"
keywords:
  - Laravel authorization engine
  - Laravel RBAC vs ABAC
  - Spatie laravel-permission alternative
  - Laravel permission package comparison
  - explainable authorization
  - multi-tenant Laravel permissions
  - permission delegation Laravel
  - libinkk permission
  - Laravel Gate tutorial
  - Filament authorization
tags:
  - laravel
  - authorization
  - rbac
  - abac
  - spatie
  - bouncer
  - laratrust
  - policies
  - permissions
  - multi-tenancy
  - delegation
  - filament
  - vue
  - react
  - security
  - tutorial
---

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://www.libinkk.in/#organization",
      "name": "Libinkk",
      "url": "https://www.libinkk.in",
      "email": "libinkk1999@gmail.com"
    },
    {
      "@type": "WebSite",
      "@id": "https://www.libinkk.in/#website",
      "url": "https://www.libinkk.in",
      "name": "Libinkk",
      "publisher": { "@id": "https://www.libinkk.in/#organization" }
    },
    {
      "@type": "WebPage",
      "@id": "https://www.libinkk.in/permission#webpage",
      "url": "https://www.libinkk.in/permission",
      "name": "Laravel Authorization Engine Tutorial | libinkk/permission",
      "description": "Why Laravel role packages are not enough. libinkk/permission decides if this user may act on this resource, in this tenant, and explains why.",
      "inLanguage": "en-IN",
      "isPartOf": { "@id": "https://www.libinkk.in/#website" },
      "about": { "@id": "https://www.libinkk.in/permission#software" },
      "breadcrumb": { "@id": "https://www.libinkk.in/permission#breadcrumb" }
    },
    {
      "@type": "TechArticle",
      "@id": "https://www.libinkk.in/permission#article",
      "headline": "Laravel Authorization Engine: Goal, Problem, Comparison, and Full Feature Tutorial",
      "description": "Official libinkk/permission 1.0 tutorial: main goal, current Laravel ACL problems, how the engine solves them, comparison with Spatie, Bouncer, Laratrust, and policies, plus every feature with a real example.",
      "url": "https://www.libinkk.in/permission",
      "mainEntityOfPage": { "@id": "https://www.libinkk.in/permission#webpage" },
      "datePublished": "2026-09-05",
      "dateModified": "2026-09-05",
      "author": { "@type": "Person", "name": "Libin K K", "url": "https://www.libinkk.in" },
      "publisher": { "@id": "https://www.libinkk.in/#organization" },
      "keywords": "Laravel authorization engine, Spatie laravel-permission alternative, Laravel RBAC, Laravel ABAC, libinkk permission",
      "articleSection": "Tutorial",
      "inLanguage": "en-IN"
    },
    {
      "@type": "SoftwareApplication",
      "@id": "https://www.libinkk.in/permission#software",
      "name": "libinkk/permission",
      "applicationCategory": "DeveloperApplication",
      "operatingSystem": "PHP 8.1+",
      "softwareVersion": "1.0.0",
      "url": "https://www.libinkk.in/permission",
      "downloadUrl": "https://packagist.org/packages/libinkk/permission",
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" }
    },
    {
      "@type": "BreadcrumbList",
      "@id": "https://www.libinkk.in/permission#breadcrumb",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://www.libinkk.in" },
        { "@type": "ListItem", "position": 2, "name": "Permission", "item": "https://www.libinkk.in/permission" }
      ]
    },
    {
      "@type": "FAQPage",
      "@id": "https://www.libinkk.in/permission#faq",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "What is the main goal of libinkk/permission?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "To decide whether this user may perform this action on this resource, in the current tenant and conditions, and to explain why. Not only to store roles."
          }
        },
        {
          "@type": "Question",
          "name": "How is libinkk/permission different from Spatie laravel-permission?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Spatie is an excellent role and permission assignment library. libinkk/permission is an authorization engine: fail-closed decisions, ABAC conditions, explicit deny, tenants, delegation, expiry, and explain()."
          }
        },
        {
          "@type": "Question",
          "name": "Is Blade or Vue a security boundary?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "No. Blade, Vue, React, and Filament only hide UI. Every write must be checked with $user->can(), Gate, or middleware."
          }
        },
        {
          "@type": "Question",
          "name": "Should I replace Laravel policies?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Dotted abilities such as invoice.approve are resolved by this package. Keep policies for non-dotted model methods if you want. Policies are not an engine step today."
          }
        }
      ]
    }
  ]
}
</script>

# Laravel Authorization Engine Tutorial: libinkk/permission

**Canonical URL:** [https://www.libinkk.in/permission](https://www.libinkk.in/permission)  
**Home:** [https://www.libinkk.in](https://www.libinkk.in)  
**Package:** `libinkk/permission` **1.0.0** · Laravel 10–13 · PHP 8.1+  
**Tags:** laravel, authorization, rbac, abac, spatie, permissions, multi-tenancy, delegation

This page is the full tutorial for [libinkk/permission](https://www.libinkk.in/permission): the **main goal**, the **problem** in typical Laravel apps, **how this engine solves it**, an honest **comparison** with Spatie, Bouncer, Laratrust, and Laravel policies, then **every feature** with a title, description, and a real-time example.

---

## Table of contents

1. [Main goal](#main-goal)
2. [The current problem](#the-current-problem)
3. [How this package solves it](#how-this-package-solves-it)
4. [Compare with other packages](#compare-with-other-packages)
5. [Install](#install)
6. [All features](#all-features)
7. [End-to-end tutorials](#end-to-end-tutorials)
8. [FAQ](#faq)
9. [SEO fields](#seo-fields)

---

## Main goal

**Goal:** ship **authorization infrastructure**, not another roles table helper.

The engine must answer one question, every time:

> Is **this user** allowed to perform **this action** on **this resource**, in the **current tenant / scope**, under the **current conditions** — and **if not, why**?

```php
$user->can('invoice.approve', $invoice);
$user->explain('invoice.approve', $invoice);
```

USP: **authorization that explains itself.**

It is **not** the goal to:

- Own your `users` table
- Replace Laravel authentication
- Treat Blade, Vue, React, or Filament as a security boundary
- Be “another Spatie-style roles package”

It **is** the goal to:

- Fail closed when the answer is unsafe
- Keep the **database** as source of truth
- Return a structured `Decision` (`allowed`, `reason`, `source`, `checks`)
- Isolate tenants and guards
- Support RBAC + ABAC + time + delegation in one path

Official site: [www.libinkk.in](https://www.libinkk.in) · tutorial: [www.libinkk.in/permission](https://www.libinkk.in/permission).

---

## The current problem

Most Laravel teams start with one of these:

| What teams install | What they actually needed |
|--------------------|---------------------------|
| Spatie `laravel-permission` or Bouncer | “Does the user have `posts.edit`?” |
| A `roles` pivot and `if ($user->role === 'admin')` | A decision for **this invoice**, **this company** |
| A growing `InvoicePolicy` | Limits, expiry, delegation, audit, cache |
| Hidden buttons in Vue | A 403 on `POST /invoices/4821/approve` |

### What breaks in production

**1. Permission ≠ authorization.**  
Rahul has `invoice.approve`. The invoice is ₹1,25,000. His limit is ₹50,000. A boolean `hasPermissionTo` still says true. Finance needs a **deny with a reason**.

**2. No tenant on the check.**  
Priya is editor in Org North and viewer in Org South. A global `can('posts.create')` leaks North rights into South unless every query is wrapped by hand.

**3. Leave cover is a copy-paste role.**  
Rahul is away. Someone assigns Anika the finance role for a week, then forgets to remove it. There is no expiry, no “only this invoice”, no revoke-by-delegator rule.

**4. UI is treated as security.**  
`v-if="can('users.delete')"` hides a button. The API still deletes if the client calls it.

**5. Nobody can explain a 403.**  
Support asks “why was this denied?” The log says `false`. No role, no condition, no tenant, no expired grant.

**6. Cache and Octane leak state.**  
A worker keeps the last tenant. The next job authorizes the wrong company.

**7. Policies grow into an untestable forest.**  
Each model policy re-implements roles, ownership, and “is admin?” until two policies disagree.

That is the **current problem**: Laravel has great authn and a Gate, but most permission packages stop at **assignment**. Production apps need a **decision**.

---

## How this package solves it

`libinkk/permission` sits on the Gate. Dotted names such as `invoice.approve` go through `AuthorizationEngine`. The path is always:

```text
User → context (tenant / scope) → permission map
    → explicit deny / allow → roles → inheritance
    → conditions / ownership → expiry → delegation
    → Decision { allowed, reason, source, checks }
    → L1 / L2 / optional L3 cache
```

If anything is missing or throws, the result is **DENY** (`CONTEXT_MISSING`, `CONDITION_FAILED`, …).

```php
$decision = $user->authorizeFor('invoice.approve', $invoice);

$decision->allowed(); // bool
$decision->reason;    // CONDITION_FAILED
$decision->source;    // role:finance-manager | direct | delegation | engine
$decision->toArray();
```

**How each production pain is handled**

| Pain | Solution in this package |
|------|--------------------------|
| Has permission but over limit | ABAC `Condition::define()` + `.when()` |
| Same user, two companies | `AuthorizationContext::tenant()` + `teams.enabled` |
| Weekend auditor | `givePermissionTo(..., expiresAt:)` |
| Manager on leave | `$user->delegate(..., until:, resource:)` |
| Hidden button only | Middleware / `$this->authorize()` still required |
| “Why 403?” | `explain()`, `debugAuthorization()`, `permission:explain` |
| Stale allow after revoke | After-commit cache invalidation + generation bump |
| Octane / queue mix-up | Flush tenant, impersonation, request cache per job |
| Who changed policy? | Permission versions + optional audit log |

Blade / Vue / React / Filament **only hide UI**. The write path is still Laravel → this package → ALLOW | DENY.

---

## Compare with other packages

This is an engineering comparison, not an attack. Spatie, Bouncer, and Laratrust are good at **storing and checking role membership**. Laravel policies are good at **model methods you write yourself**. This package is an **engine**.

| Capability | libinkk/permission | Spatie laravel-permission | Silber Bouncer | Laratrust | Laravel Policies / Gate |
|------------|--------------------|---------------------------|----------------|-----------|-------------------------|
| Assign roles / permissions | Yes | Yes | Yes | Yes | You write it |
| `$user->can()` / Gate | Yes (dotted names) | Via Gate | Yes | Yes | Yes |
| Structured `Decision` + `explain()` | **Yes** | No | No | No | You return true/false |
| Fail closed on errors | **Yes** | App-dependent | App-dependent | App-dependent | You choose |
| Explicit deny vs allow | **Yes** (configurable precedence) | Extra work | Ability/forbid | Extra work | You write deny |
| ABAC conditions | **Yes** | You write in policy | You write | You write | Yes, in policy |
| Ownership (`.own`) | **Yes** | Policy | Policy | Policy | Yes |
| Multi-tenant context | **Yes** (`AuthorizationContext`) | Teams (optional) | Scope | Teams | You write |
| Hierarchical scopes | **Yes** | Limited | Limited | Limited | You write |
| Temporary `startsAt` / `expiresAt` | **Yes** | You write | You write | You write | You write |
| Delegation (no self / no re-delegate) | **Yes** | You write | You write | You write | You write |
| Append-only audit | **Yes** (opt-in) | You write | You write | You write | You write |
| Permission version / rollback | **Yes** | No | No | No | No |
| Wildcard `posts.*` | **Yes** | Partial / conventions | Partial | Partial | No |
| Role inheritance + cycle reject | **Yes** | Extra | Extra | Extra | No |
| Vue / React UI helpers | **Yes** (not a grant) | Community | No | No | No |
| Optional Filament adapter | **Yes** | Plugins | No | No | You write |
| Debugger / graph / unused CLI | **Yes** | Limited | Limited | Limited | No |
| Layered cache + Octane flush | **Yes** | Cache | Cache | Cache | No |
| Owns `users` table | **No** | No | No | No | No |
| Best at | Decisions | Assignment UX | Elegant abilities | Teams + roles | Per-model PHP |

### When to keep another package

- You only need “user has role editor” and a few Blade `@role` checks — Spatie or Bouncer is enough.
- Your rules are three methods on `PostPolicy` and will stay that small — use Laravel policies.
- You already run Spatie and only want assignment screens — do not rewrite for fashion.

### When this engine is the fit

- The same permission **changes meaning** with amount, owner, tenant, or time.
- You must **explain** denials to support or compliance.
- You need **delegation** and **expiry** without cloning roles.
- You run **Octane** or queues and cannot leak tenant state.
- You want one path for Blade, Vue, React, Filament, and the API — UI never grants.

You can still call a Laravel policy **after** this engine if the host app wants both. `POLICY_DENIED` exists as a reason code; policies are not an automatic engine step in 1.0.

---

## Install

```bash
composer require libinkk/permission:^1.0
php artisan permission:install --migrate
php artisan permission:doctor
```

Set `permission.database.primary_key` (`bigint` / `uuid` / `ulid`) **before** the first migrate.

```php
use Libinkk\Permission\Concerns\HasAuthorization;

class User extends Authenticatable
{
    use HasAuthorization;
}
```

Compatibility: Laravel 10–13, PHP 8.1+ as on [www.libinkk.in/permission](https://www.libinkk.in/permission).

---

## All features

Each block is **title → what it is → real-time example**.

### Fail-closed authorization engine

**Description.** If the engine cannot decide safely (blank name, thrown condition, missing required tenant), it returns DENY. Cache write failures never grant.

**Realtime.** A broken `within_approval_limit` callback throws. Rahul does **not** approve the invoice. Reason: `CONDITION_FAILED` / fail-closed evaluate.

```php
$user->can('invoice.approve', $invoice); // false if the condition throws
```

### Explainable decisions

**Description.** `authorizeFor()` returns a `Decision`. `explain()` is the array form. Support can see reason, source, role, scope, and condition checks.

**Realtime.** Support ticket: “Why was invoice #4821 rejected?”

```php
$d = $rahul->explain('invoice.approve', $invoice);
// allowed: false
// reason: CONDITION_FAILED
// source: role:finance-manager
```

### RBAC (roles and role permissions)

**Description.** Roles hold permissions. Users get roles. Classic Laravel RBAC, used as one **layer** of the engine, not the whole story.

**Realtime.** Meera is `editor`. She can update posts, not delete them.

```php
Role::findOrCreate('editor')->givePermissionTo('posts.view', 'posts.create', 'posts.update');
$meera->assignRole('editor');
$meera->can('posts.update'); // true
$meera->can('posts.delete'); // false
```

### Direct user permissions

**Description.** Grant a permission on the user without a role. Same precedence rules (deny still wins).

**Realtime.** One auditor needs `reports.export` without becoming admin.

```php
$auditor->givePermissionTo('reports.export');
```

### Permission resources and groups

**Description.** `resource.action` naming. `defineResource()` / `crud()` create a set. Groups are metadata for admin UIs and exports.

**Realtime.** Marketing asks for “all Posts permissions”.

```php
Permission::crud('posts');
Permission::defineResource('posts', ['view', 'create', 'update', 'delete', 'publish'], ['group' => 'Posts']);
Permission::inGroup('Posts');
```

```bash
php artisan permission:resource posts --actions=view,create,update,delete,publish --group=Posts
```

### Wildcards

**Description.** `posts.*` matches `posts.delete`. It does not match `invoices.view`. Exact beats wildcard.

**Realtime.** Super admin gets `posts.*` and still cannot touch payroll.

```php
$admin->givePermissionTo('posts.*');
$admin->can('posts.publish');     // true
$admin->can('employees.salary.view'); // false
```

### Role hierarchy

**Description.** `Role::inherit('admin', 'manager')` means admin inherits manager’s permissions. Cycles are rejected.

**Realtime.** Admin sees everything a manager sees, plus more.

```php
Role::inherit('admin', 'manager');
Role::inherit('manager', 'editor');
```

```bash
php artisan permission:graph
```

### Explicit deny

**Description.** A deny on the user or role beats an allow at a lower layer. Precedence is configurable.

**Realtime.** Editors can delete drafts, except Meera (legal hold).

```php
$role->givePermissionTo('posts.delete');
$meera->assignRole($role);
$meera->denyPermissionTo('posts.delete');
$meera->can('posts.delete'); // false — EXPLICIT_DENY
```

### ABAC conditions

**Description.** Named or closure conditions run after the permission exists. Failed or thrown conditions deny.

**Realtime.** Finance limit ₹50,000 vs invoice ₹1,25,000.

```php
Condition::define('within_approval_limit', function ($user, $invoice) {
    return (int) $invoice->amount <= (int) $user->approval_limit;
});
Permission::define('invoice.approve')->when('within_approval_limit');
```

### Ownership (`.own`)

**Description.** `posts.update.own` checks owner fields (`author_id`, `user_id`, `owner_id`, or config).

**Realtime.** An author edits their post, not a colleague’s.

```php
$author->can('posts.update.own', $ownPost);   // true
$author->can('posts.update.own', $otherPost); // false — RESOURCE_DENIED
```

### Multi-tenancy

**Description.** Optional `teams`. Assignments can carry a tenant. `AuthorizationContext::tenant()` selects the boundary. Mismatch denies.

**Realtime.** Priya is editor in North, viewer in South.

```php
$priya->assignRole('editor', $north);
$priya->assignRole('viewer', $south);
AuthorizationContext::tenant($north);
$priya->can('posts.create'); // true
AuthorizationContext::switch($south);
$priya->can('posts.create'); // false
```

### Hierarchical scopes

**Description.** Organization → workspace → project via `scopes.parent_id`. Child does not grant parent.

**Realtime.** Org admin may apply to a workspace when inheritance is on; a project member does not become org admin.

```php
$orgScope = Scope::for($organization, 'organization');
$wsScope = Scope::for($workspace, 'workspace', $orgScope);
AuthorizationContext::scope($workspace);
```

### Authorization context

**Description.** Request-scoped tenant/scope. Flushed on Octane and queue jobs. Impersonation records **identity only** (not a grant).

**Realtime.** A job must set the company again.

```php
AuthorizationContext::tenant($this->organization);
AuthorizationContext::impersonating($admin); // audit metadata only
```

### Global vs tenant roles

**Description.** A role with no tenant is global. It crosses tenants only if `teams.global_roles.cross_tenant` is true.

**Realtime.** Platform `super-admin` vs company `editor`.

### Temporary access

**Description.** `startsAt` / `expiresAt` on user permissions and roles. After expiry: `EXPIRED_PERMISSION`.

**Realtime.** Auditor export Friday 18:00–Monday 09:00.

```php
$auditor->givePermissionTo(
    'reports.export',
    startsAt: now()->friday()->setTime(18, 0),
    expiresAt: now()->next('Monday')->setTime(9, 0),
);
```

### Delegation

**Description.** Delegator must hold the permission. No self-delegate. No re-delegate. Only the delegator revokes. If the delegator loses the permission, the delegatee loses it.

**Realtime.** Rahul on leave; Anika covers one invoice for 8 hours.

```php
$grant = $rahul->delegate('invoice.approve', to: $anika, until: now()->addHours(8), resource: $small);
$rahul->revokeDelegation($grant);
```

### Audit log

**Description.** Opt-in `authorization_audits`. Assignment and delegation events. Decision logging is separate and off by default. Rows cannot be updated or deleted.

**Realtime.** Compliance asks who granted `users.delete` last Tuesday.

```php
'audit' => ['enabled' => true, 'decisions' => false],
```

### Permission versioning and history

**Description.** Snapshots on change. `history()` and `rollbackTo(1)`.

**Realtime.** Someone removed the approval condition; roll back.

```php
$permission->history();
$permission->rollbackTo(1);
```

### Laravel Gate

**Description.** `Gate::before` handles dotted abilities. `$this->authorize('posts.create')` uses the engine.

**Realtime.** Same check in controller and `$user->can()`.

### Middleware

**Description.** `permission:` and `role:` aliases. Pipe = OR (configurable AND).

**Realtime.**

```php
Route::post('/posts', [PostController::class, 'store'])
    ->middleware(['auth', 'permission:posts.create']);
```

### Blade

**Description.** `@can`, `@role`, `@canany`, `@canall`, `@permissionPayload`. UI only.

**Realtime.** Hide “New post” for viewers. Still protect `store()`.

### Vue helpers

**Description.** `$can` / `usePermission()`. Payload from `@permissionPayload`. Not a grant.

**Realtime.**

```vue
<button v-if="$can('posts.create')">Create</button>
```

### React helpers

**Description.** `PermissionProvider`, `<Can>`, `<CanAny>`, `<CanAll>`. Not a grant.

**Realtime.**

```jsx
<Can permission="posts.create"><CreatePostButton /></Can>
```

### Authorization API

**Description.** Off by default. `GET /api/authorization`, `/api/users/{user}/access` (self-only), `/api/permissions/matrix`.

**Realtime.** SPA boots with the current user’s matrix. Viewing another user requires `frontend.access_user_permission`.

### Filament adapter

**Description.** Optional, no Filament Composer dep. Resource/page/widget/relation traits. Actions/forms/tables/bulk. Tenant sync. Still `$user->can()` on the server.

**Realtime.**

```php
class PostResource extends Resource
{
    use AuthorizesFilamentResource;
    protected static ?string $permissionResource = 'posts';
}
```

### PHP attribute discovery

**Description.** `#[Permission('posts.publish')]` on controllers/actions. `permission:discover` / `sync`.

**Realtime.** New `publish()` method is found in CI and created in the DB.

### Artisan DX

**Description.** install, resource, discover, sync, validate, doctor, graph, unused, explain, cache, cache:clear, export.

**Realtime.** Before deploy: `permission:doctor` and `permission:validate`.

### Authorization debugger

**Description.** `debugAuthorization()` / `permission:explain` / optional explain API. Lists role, source, tenant, conditions, deny, expiry, delegation.

**Realtime.** Filament page shows why invoice #4821 was denied.

### Permission graph

**Description.** Role tree + per-role permissions. Text or JSON.

```bash
php artisan permission:graph --json
```

### Unused permissions

**Description.** Never assigned, inactive, not in code (if discovery paths set), assigned to empty roles.

```bash
php artisan permission:unused
```

### Bulk authorization and preload

**Description.** Load the map once. `permissionsFor('posts')` for UI matrices. `authorizeMany()` for a list of records.

**Realtime.** Index page of 50 posts: who can update each row without 50 full map rebuilds.

```php
$user->preloadAuthorization();
$user->permissionsFor('posts');
$user->authorizeMany('posts.update', $posts);
```

### Layered cache

**Description.** L1 request → L2 store → optional L3 Redis (opt-in). After-commit invalidation. Stampede lock. Metrics in doctor.

**Realtime.** Second `can('posts.view')` in the same request is memory. Revoke then `can()` in the same request is deny.

### Octane and queue safety

**Description.** Flush request cache, tenant, impersonation, fakes, metrics on Octane ticks and queue job events.

**Realtime.** Job B does not inherit Job A’s `AuthorizationContext::tenant()`.

### UUID / ULID keys

**Description.** `permission.database.primary_key`. Pivot rows get generated ids. Users stay on your key type.

**Realtime.** Greenfield SaaS uses UUID roles without owning `users`.

### Multiple guards

**Description.** `guard_name` on roles and permissions. `web` does not authorize `api`.

**Realtime.** Sanctum API user cannot use a `web` editor role.

### Testing helpers

**Description.** `Permission::fake()`, `allow` / `deny`, `assertCan` / `assertCannot`. Fake blocked in production.

**Realtime.** Feature test for the approve button without seeding the full matrix.

```php
Permission::fake();
Permission::allow('invoice.approve');
$this->assertCan($user, 'invoice.approve', $invoice);
```

### Events

**Description.** Role/permission lifecycle, grant/revoke, delegation, allowed/denied, policy changed.

**Realtime.** Listen to `RoleAssigned` to notify Slack.

### System record protection

**Description.** `is_system` roles/permissions cannot be deleted or unprotected.

**Realtime.** Seeder `super-admin` survives a confused admin panel delete.

### User access export

**Description.** Full dump: roles, directs, denials, effective (wildcard expanded), groups, resources, temporary, delegations.

```php
$export = $user->exportAccess();
```

```bash
php artisan permission:export 10 --format=table
```

### Doctor and validate

**Description.** Health: tables, cache, unused, stale delegations, version. Validate: duplicates, orphans, cycles.

```bash
php artisan permission:doctor
php artisan permission:validate
```

---

## End-to-end tutorials

### Tutorial A — Company blog (RBAC)

**Story.** Acme marketing. Viewers read. Editors write. Admins delete and publish.

```php
Permission::defineResource('posts', ['view', 'create', 'update', 'delete', 'publish'], ['group' => 'Posts']);

Role::findOrCreate('viewer')->givePermissionTo('posts.view');
Role::findOrCreate('editor')->givePermissionTo('posts.view', 'posts.create', 'posts.update');
Role::findOrCreate('admin')->givePermissionTo('posts.*');

$meera->assignRole('editor');
```

```php
Route::post('/posts', [PostController::class, 'store'])
    ->middleware(['auth', 'permission:posts.create']);

public function store(Request $request)
{
    $this->authorize('posts.create');
    return Post::query()->create($request->validated());
}
```

```blade
@can('posts.create')
    <a href="{{ route('posts.create') }}">New post</a>
@endcan
```

`@can` hides the link. Middleware and `authorize()` are the grant check.

### Tutorial B — Invoice desk (ABAC + delegation)

**Story.** Rahul, Finance Manager, limit ₹50,000. Invoice #4821 is ₹1,25,000. Next week he delegates a **small** bill to Anika for 8 hours.

```php
Permission::define('invoice.approve', ['group' => 'Finance', 'is_dangerous' => true]);
Condition::define('within_approval_limit', fn ($user, $invoice) =>
    (int) $invoice->amount <= (int) $user->approval_limit
);
Permission::define('invoice.approve')->when('within_approval_limit');

$rahul->givePermissionTo('invoice.approve');
$rahul->approval_limit = 50_000;

$small = Invoice::query()->create(['amount' => 20_000]);
$large = Invoice::query()->create(['amount' => 125_000]);

$rahul->can('invoice.approve', $small); // true
$rahul->can('invoice.approve', $large); // false — CONDITION_FAILED

$grant = $rahul->delegate(
    'invoice.approve',
    to: $anika,
    until: now()->addHours(8),
    reason: 'On leave',
    resource: $small,
);
```

```bash
php artisan permission:explain 10 invoice.approve
```

### Tutorial C — Two companies (tenants)

**Story.** Priya is editor in North and viewer in South.

```php
'teams' => ['enabled' => true, 'require_context' => true],
```

```php
$priya->assignRole('editor', $north);
$priya->assignRole('viewer', $south);

AuthorizationContext::tenant($north);
$priya->can('posts.create'); // true

AuthorizationContext::switch($south);
$priya->can('posts.create'); // false
```

Set tenant in HTTP middleware **and** in every queued job.

### Tutorial D — HR salary column

**Story.** Managers see the employee list. Only HR sees salary.

```php
Permission::define('employees.view');
Permission::define('employees.salary.view', ['is_dangerous' => true]);

return [
    'name' => $employee->name,
    'salary' => $request->user()->can('employees.salary.view')
        ? $employee->salary
        : null,
];
```

Hiding a Filament column is not enough if the JSON still includes `salary`.

### Tutorial E — Weekend auditor

```php
$auditor->givePermissionTo(
    'reports.export',
    startsAt: now()->friday()->setTime(18, 0),
    expiresAt: now()->next('Monday')->setTime(9, 0),
);
```

After Monday 09:00: `EXPIRED_PERMISSION`. No cron required for the deny.

---

## FAQ

**What is the main goal?**  
Decide and **explain** authorization for this user, action, resource, tenant, and conditions.

**What problem does it replace?**  
The gap between “has role” and “allowed to approve **this** invoice in **this** company **now**”.

**Is it a Spatie replacement?**  
Only if you need an engine (deny, ABAC, tenants, delegation, explain). Spatie remains a strong assignment library.

**Is Vue secure?**  
No. UI-only. Enforce on the server.

**Do you own users?**  
No. Polymorphic `user_type` / `user_id`.

**Which Laravel versions?**  
10–13, PHP 8.1+ as documented on this page.

**How do I report a bad grant?**  
libinkk1999@gmail.com — see [SECURITY.md](../SECURITY.md).

---

## SEO fields

Use on [https://www.libinkk.in/permission](https://www.libinkk.in/permission).

| Field | Value |
|-------|--------|
| **Meta title** | Laravel Authorization Engine Tutorial \| libinkk/permission |
| **Meta description** | Why Laravel role packages are not enough. libinkk/permission decides if this user may act on this resource, in this tenant, and explains why. Full feature tutorial. |
| **Canonical** | https://www.libinkk.in/permission |
| **Keywords** | Laravel authorization engine, Spatie laravel-permission alternative, Laravel RBAC, Laravel ABAC, libinkk permission, multi-tenant Laravel, permission delegation |
| **Tags** | laravel, authorization, rbac, abac, spatie, bouncer, laratrust, policies, permissions, multi-tenancy, delegation, filament, vue, react, security, tutorial |

```json
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Laravel Authorization Engine: Goal, Problem, Comparison, and Full Feature Tutorial",
  "description": "Why Laravel role packages are not enough. libinkk/permission decides if this user may act on this resource, in this tenant, and explains why.",
  "url": "https://www.libinkk.in/permission",
  "datePublished": "2026-09-05",
  "dateModified": "2026-09-05",
  "inLanguage": "en-IN",
  "keywords": [
    "Laravel authorization engine",
    "Spatie laravel-permission alternative",
    "Laravel RBAC",
    "Laravel ABAC",
    "libinkk permission"
  ],
  "author": { "@type": "Person", "name": "Libin K K", "url": "https://www.libinkk.in" },
  "publisher": { "@type": "Organization", "name": "Libinkk", "url": "https://www.libinkk.in" }
}
```

```json
{
  "og:title": "Laravel Authorization Engine Tutorial | libinkk/permission",
  "og:description": "Goal, problem, and solution: compare Spatie, Bouncer, Laratrust, and Laravel policies with libinkk/permission 1.0.",
  "og:url": "https://www.libinkk.in/permission",
  "og:type": "article",
  "og:site_name": "Libinkk",
  "twitter:card": "summary_large_image"
}
```

---

*libinkk/permission 1.0 · [www.libinkk.in](https://www.libinkk.in) · [www.libinkk.in/permission](https://www.libinkk.in/permission)*
