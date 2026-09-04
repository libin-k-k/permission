<?php

namespace Libinkk\Permission\Frontend;

use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\UserAccessExporter;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Scopes\ScopeResolver;
use Libinkk\Permission\Support\WildcardMatcher;

class FrontendPayload
{
    public function __construct(
        protected UserAccessExporter $exporter,
        protected PermissionResolver $resolver,
        protected ScopeResolver $scopes,
    ) {
    }

    /**
     * Compact authorization payload for Vue / React / GET /api/authorization.
     *
     * @return array<string, mixed>
     */
    public function for(?object $user, ?string $guard = null): array
    {
        if (! is_object($user) || ! method_exists($user, 'getKey')) {
            return $this->empty();
        }

        $export = $this->exporter->export($user, $guard);
        $permissions = [];
        $denials = [];

        foreach ($export['effective_permissions'] as $name => $entry) {
            if (($entry['effect'] ?? 'allow') === 'deny') {
                $denials[] = $name;

                continue;
            }

            $permissions[] = $name;
        }

        sort($permissions);
        sort($denials);

        $roles = collect($export['roles'])
            ->map(fn (array $role) => $role['slug'])
            ->values()
            ->all();

        return [
            'user' => [
                'id' => $export['user']['id'] ?? $user->getKey(),
            ],
            'roles' => $roles,
            'permissions' => $permissions,
            'denials' => array_values(array_unique(array_merge($export['denials'], $denials))),
            'scopes' => $this->scopesPayload(),
            'resources' => $this->resourceMap($permissions, $denials, $export['effective_permissions']),
        ];
    }

    /**
     * User-access shape for GET /api/users/{user}/access.
     *
     * @return array<string, mixed>
     */
    public function access(object $user, ?string $guard = null): array
    {
        $export = $this->exporter->export($user, $guard);
        $compact = $this->for($user, $guard);

        return [
            'roles' => $compact['roles'],
            'permissions' => $compact['permissions'],
            'scopes' => $compact['scopes'],
            'temporary' => $export['temporary'],
            'delegations' => $export['delegations'],
            'denials' => $compact['denials'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function empty(): array
    {
        return [
            'user' => null,
            'roles' => [],
            'permissions' => [],
            'denials' => [],
            'scopes' => [
                'tenant' => null,
                'label' => $this->scopes->label(),
            ],
            'resources' => [],
        ];
    }

    /**
     * @return array{tenant: mixed, label: ?string}
     */
    protected function scopesPayload(): array
    {
        $target = AuthorizationContext::currentTarget();
        $tenant = null;

        if (is_object($target) && method_exists($target, 'getKey')) {
            $tenant = $target->getKey();
        }

        return [
            'tenant' => $tenant,
            'label' => $this->scopes->label(),
        ];
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $denials
     * @param  array<string, array<string, mixed>>  $effective
     * @return array<string, array<string, bool>>
     */
    protected function resourceMap(array $permissions, array $denials, array $effective): array
    {
        $resources = [];

        foreach ($effective as $name => $entry) {
            if (WildcardMatcher::isWildcard($name)) {
                continue;
            }

            $resource = $entry['resource'] ?? $this->resourceFromName($name);
            $action = $this->actionFromName($name);

            $allowed = ($entry['effect'] ?? 'allow') !== 'deny'
                && ! $this->isDenied($name, $denials);

            $resources[$resource][$action] = $allowed;
        }

        ksort($resources);

        foreach ($resources as &$actions) {
            ksort($actions);
        }

        return $resources;
    }

    /**
     * @param  list<string>  $denials
     */
    protected function isDenied(string $name, array $denials): bool
    {
        foreach ($denials as $pattern) {
            if ($pattern === $name || WildcardMatcher::matches($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    protected function resourceFromName(string $name): string
    {
        return str_contains($name, '.') ? explode('.', $name, 2)[0] : $name;
    }

    protected function actionFromName(string $name): string
    {
        if (! str_contains($name, '.')) {
            return $name;
        }

        return explode('.', $name, 2)[1];
    }
}
