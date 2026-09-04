<?php

namespace Libinkk\Permission\Debug;

use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Authorization\UserAccessExporter;
use Libinkk\Permission\Contracts\AuthorizationEngine;
use Libinkk\Permission\Delegation\DelegationManager;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Scopes\ScopeResolver;

class AuthorizationDebugger
{
    public function __construct(
        protected AuthorizationEngine $engine,
        protected PermissionResolver $resolver,
        protected UserAccessExporter $exporter,
        protected DelegationManager $delegations,
        protected ScopeResolver $scopes,
    ) {
    }

    /**
     * Structured authorization debug report for CLI, API, Filament, Telescope, and DebugBar.
     *
     * @return array<string, mixed>
     */
    public function debug(object $user, string $permission, mixed $resource = null): array
    {
        $arguments = $resource === null ? [] : [$resource];
        $decision = $this->engine->decide($user, $permission, $arguments);
        $guard = $this->guardFor($user);
        $map = $this->resolver->permissionMapFor($user, $guard);
        $entry = $this->resolver->matchPermission($map, $permission);
        $access = $this->exporter->export($user, $guard);
        $roles = $this->resolver->rolesFor($user, $guard);
        $delegation = $this->delegations->activeFor($user, $permission, $resource);

        $inherited = [];
        $direct = [];
        foreach ($map as $name => $row) {
            $source = (string) ($row['source'] ?? '');
            if (str_starts_with($source, 'inherited:')) {
                $inherited[] = $name;
            }
            if ($source === 'direct') {
                $direct[] = $name;
            }
        }
        sort($inherited);
        sort($direct);

        $report = [
            'user' => $this->userCard($user),
            'action' => $permission,
            'resource' => $this->resourceCard($resource),
            'roles' => array_map(fn (array $role) => [
                'slug' => $role['slug'],
                'name' => $role['name'],
                'passed' => true,
            ], $roles),
            'permission' => [
                'name' => $permission,
                'matched' => $entry['matched'] ?? $decision->metadata['matched'] ?? null,
                'source' => $decision->source,
                'via' => $entry['via'] ?? $decision->metadata['via'] ?? null,
                'layer' => $entry['layer'] ?? $decision->metadata['layer'] ?? null,
                'passed' => (bool) ($decision->checks['permission'] ?? $decision->allowed()),
            ],
            'direct_permissions' => $direct,
            'inherited_permissions' => $inherited,
            'tenant' => $this->contextCard(
                AuthorizationContext::currentTenant(),
                ! in_array($decision->reason, [DecisionReason::TENANT_MISMATCH, DecisionReason::CONTEXT_MISSING], true)
            ),
            'scope' => $this->contextCard(
                AuthorizationContext::currentScope() ?? $decision->scope,
                ! in_array($decision->reason, [DecisionReason::SCOPE_MISMATCH, DecisionReason::CONTEXT_MISSING], true)
            ),
            'policy' => [
                'name' => null,
                'passed' => null,
                'note' => 'Policies are not an engine step',
            ],
            'conditions' => $decision->conditions,
            'explicit_deny' => $decision->reason === DecisionReason::EXPLICIT_DENY,
            'expiration' => [
                'expired' => in_array($decision->reason, [
                    DecisionReason::EXPIRED_PERMISSION,
                    DecisionReason::DELEGATION_EXPIRED,
                ], true),
                'temporary' => $access['temporary'],
            ],
            'delegation' => $delegation,
            'decision' => $decision->toArray(),
            'final' => $decision->allowed() ? 'ALLOWED' : 'DENIED',
            'reason' => $decision->reason,
            'checks' => $this->checks($decision, $entry, $roles, $delegation),
        ];

        $report['text'] = $this->render($report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function render(array $report): string
    {
        $lines = [
            'Authorization Debugger',
            '',
            'User:',
            $this->formatUser($report['user']),
            '',
            'Action:',
            (string) $report['action'],
            '',
            'Resource:',
            $this->formatResource($report['resource']),
            '',
        ];

        $roleNames = array_map(fn (array $role) => $role['name'] ?? $role['slug'], $report['roles']);
        $lines[] = 'Role:';
        $lines[] = $this->checkedLine($roleNames === [] ? '(none)' : implode(', ', $roleNames), $report['roles'] !== []);
        $lines[] = '';
        $lines[] = 'Permission:';
        $lines[] = $this->checkedLine((string) $report['permission']['name'], (bool) $report['permission']['passed']);
        $lines[] = '';
        $lines[] = 'Tenant:';
        $lines[] = $this->checkedLine(
            (string) ($report['tenant']['label'] ?? '(none)'),
            (bool) ($report['tenant']['passed'] ?? false)
        );
        $lines[] = '';
        $lines[] = 'Scope:';
        $lines[] = $this->checkedLine(
            (string) ($report['scope']['label'] ?? '(none)'),
            (bool) ($report['scope']['passed'] ?? false)
        );
        $lines[] = '';
        $lines[] = 'Policy:';
        $lines[] = $this->checkedLine((string) ($report['policy']['note'] ?? 'n/a'), null);
        $lines[] = '';

        foreach ($report['conditions'] as $name => $passed) {
            $lines[] = $name.':';
            $lines[] = $this->checkedLine((string) $name, (bool) $passed);
            $lines[] = '';
        }

        $lines[] = 'Explicit deny:';
        $lines[] = $this->checkedLine($report['explicit_deny'] ? 'yes' : 'no', ! $report['explicit_deny']);
        $lines[] = '';
        $lines[] = 'Delegation:';
        $lines[] = $this->checkedLine(
            $report['delegation'] ? (string) ($report['delegation']['permission'] ?? 'active') : '(none)',
            $report['delegation'] !== null
        );
        $lines[] = '';
        $lines[] = 'FINAL DECISION:';
        $lines[] = (string) $report['final'];
        $lines[] = '';
        $lines[] = 'Reason:';
        $lines[] = (string) ($report['reason'] ?? '');

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param  array<string, mixed>|null  $entry
     * @param  list<array<string, mixed>>  $roles
     * @return list<array{key: string, passed: bool|null, label: string}>
     */
    protected function checks(Decision $decision, ?array $entry, array $roles, ?array $delegation): array
    {
        return [
            ['key' => 'role', 'passed' => $roles !== [], 'label' => 'Effective role'],
            ['key' => 'permission', 'passed' => (bool) ($decision->checks['permission'] ?? false), 'label' => 'Permission source'],
            ['key' => 'direct', 'passed' => ($entry['source'] ?? null) === 'direct', 'label' => 'Direct permissions'],
            ['key' => 'inherited', 'passed' => str_starts_with((string) ($entry['source'] ?? ''), 'inherited:'), 'label' => 'Inherited permissions'],
            ['key' => 'tenant', 'passed' => $decision->checks['tenant'] ?? (AuthorizationContext::currentTenant() !== null), 'label' => 'Tenant'],
            ['key' => 'scope', 'passed' => $decision->checks['scope'] ?? ($decision->scope !== null), 'label' => 'Scope'],
            ['key' => 'policy', 'passed' => null, 'label' => 'Policy result'],
            ['key' => 'conditions', 'passed' => $decision->checks['conditions'] ?? ($decision->conditions === [] ? null : ! in_array(false, $decision->conditions, true)), 'label' => 'Conditions'],
            ['key' => 'explicit_deny', 'passed' => $decision->reason !== DecisionReason::EXPLICIT_DENY, 'label' => 'Explicit deny'],
            ['key' => 'expiration', 'passed' => ! in_array($decision->reason, [DecisionReason::EXPIRED_PERMISSION, DecisionReason::DELEGATION_EXPIRED], true), 'label' => 'Expiration'],
            ['key' => 'delegation', 'passed' => $delegation !== null || (bool) ($decision->checks['delegation'] ?? false), 'label' => 'Delegation'],
            ['key' => 'decision', 'passed' => $decision->allowed(), 'label' => 'Final decision'],
        ];
    }

    /**
     * @return array{id: mixed, type: string, name: string|null, label: string}
     */
    protected function userCard(object $user): array
    {
        $id = method_exists($user, 'getKey') ? $user->getKey() : null;
        $name = is_string($user->name ?? null) ? $user->name : null;
        $type = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;

        return [
            'id' => $id,
            'type' => $type,
            'name' => $name,
            'label' => trim(($name ?? class_basename($user)).($id !== null ? ' #'.$id : '')),
        ];
    }

    /**
     * @return array{type: string|null, id: mixed, label: string}
     */
    protected function resourceCard(mixed $resource): array
    {
        if ($resource === null) {
            return ['type' => null, 'id' => null, 'label' => '(none)'];
        }

        if (! is_object($resource)) {
            return ['type' => get_debug_type($resource), 'id' => null, 'label' => (string) $resource];
        }

        $id = method_exists($resource, 'getKey') ? $resource->getKey() : null;

        return [
            'type' => $resource::class,
            'id' => $id,
            'label' => class_basename($resource).($id !== null ? ' #'.$id : ''),
        ];
    }

    /**
     * @return array{label: string|null, passed: bool}
     */
    protected function contextCard(mixed $value, bool $passed): array
    {
        if ($value === null || $value === '') {
            return ['label' => null, 'passed' => $passed];
        }

        if (is_object($value)) {
            $name = is_string($value->name ?? null) ? $value->name : class_basename($value);
            $id = method_exists($value, 'getKey') ? $value->getKey() : null;

            return [
                'label' => $name.($id !== null ? ' #'.$id : ''),
                'passed' => $passed,
            ];
        }

        return ['label' => (string) $value, 'passed' => $passed];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function formatUser(array $user): string
    {
        return (string) ($user['label'] ?? 'unknown');
    }

    /**
     * @param  array<string, mixed>|null  $resource
     */
    protected function formatResource(?array $resource): string
    {
        return (string) ($resource['label'] ?? '(none)');
    }

    protected function checkedLine(string $label, ?bool $passed): string
    {
        $mark = $passed === null ? '—' : ($passed ? '✓' : '✗');

        return sprintf('%-32s %s', $label, $mark);
    }

    protected function guardFor(object $user): string
    {
        if (method_exists($user, 'authorizationGuard')) {
            return (string) $user->authorizationGuard();
        }

        if (isset($user->guard_name) && is_string($user->guard_name) && $user->guard_name !== '') {
            return $user->guard_name;
        }

        return (string) config('permission.default_guard', 'web');
    }
}
