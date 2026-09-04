<?php

namespace Libinkk\Permission\Cache;

use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Contracts\PermissionCache as PermissionCacheContract;
use Throwable;

class DecisionCache
{
    public function __construct(
        protected PermissionCacheContract $cache,
    ) {
    }

    public function get(string $key, bool $hasResource): ?Decision
    {
        try {
            $value = $this->cache->get($key, persistent: ! $hasResource && $this->persistentEnabled());
        } catch (Throwable) {
            return null;
        }

        return $this->hydrate($value);
    }

    public function put(string $key, Decision $decision, bool $hasResource): void
    {
        $persistent = ! $hasResource && $this->persistentEnabled();

        try {
            $this->cache->put($key, $decision->toArray(), 'decisions', $persistent);
        } catch (Throwable) {
            // Ignore cache write failures; the database remains source of truth.
        }
    }

    public function keyFor(string $userKey, string $permission, string $guard, ?string $resourceKey): string
    {
        $payload = implode('|', [
            $userKey,
            $guard,
            $permission,
            $resourceKey ?? '',
        ]);

        return 'authz:'.sha1($payload);
    }

    protected function persistentEnabled(): bool
    {
        return (bool) config('permission.cache.decision_cache.enabled', true);
    }

    protected function hydrate(mixed $value): ?Decision
    {
        if ($value instanceof Decision) {
            return $value;
        }

        if (! is_array($value) || ! array_key_exists('allowed', $value)) {
            return null;
        }

        return new Decision(
            allowed: (bool) $value['allowed'],
            permission: (string) ($value['permission'] ?? ''),
            user: $value['user_id'] ?? $value['user'] ?? null,
            role: $value['role'] ?? null,
            scope: $value['scope'] ?? null,
            resource: $value['resource'] ?? null,
            conditions: $value['conditions'] ?? [],
            reason: $value['reason'] ?? null,
            source: $value['source'] ?? null,
            metadata: $value['metadata'] ?? [],
            checks: $value['checks'] ?? [],
        );
    }
}
