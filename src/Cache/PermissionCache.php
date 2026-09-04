<?php

namespace Libinkk\Permission\Cache;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Contracts\PermissionCache as PermissionCacheContract;
use LogicException;
use Throwable;

class PermissionCache implements PermissionCacheContract
{
    /**
     * @var array<string, mixed>
     */
    protected array $request = [];

    /**
     * Keys mutated in this request; skip L2 until after commit.
     *
     * @var array<string, true>
     */
    protected array $dirty = [];

    /**
     * Cache keys currently being resolved; prevents re-entrant remember() loops.
     *
     * @var array<string, true>
     */
    protected array $computing = [];

    public function remember(string $key, string $ttlType, Closure $callback): mixed
    {
        if (array_key_exists($key, $this->request)) {
            return $this->request[$key];
        }

        if (isset($this->computing[$key])) {
            throw new LogicException("Circular permission cache resolution detected for key [{$key}].");
        }

        $this->computing[$key] = true;

        try {
            if (! $this->enabled() || isset($this->dirty[$key])) {
                return $this->request[$key] = $callback();
            }

            $fullKey = $this->fullKey($key);

            try {
                $cached = $this->store()->get($fullKey);

                if ($cached !== null) {
                    return $this->request[$key] = $cached;
                }
            } catch (Throwable) {
                return $this->request[$key] = $callback();
            }

            try {
                $value = $this->withLock($fullKey, function () use ($fullKey, $ttlType, $callback) {
                    $store = $this->store();
                    $cached = $store->get($fullKey);

                    if ($cached !== null) {
                        return $cached;
                    }

                    $value = $callback();
                    $store->put($fullKey, $value, $this->ttl($ttlType));

                    return $value;
                });
            } catch (Throwable) {
                $value = $callback();
            }

            return $this->request[$key] = $value;
        } finally {
            unset($this->computing[$key]);
        }
    }

    public function get(string $key, bool $persistent = true): mixed
    {
        if (array_key_exists($key, $this->request)) {
            return $this->request[$key];
        }

        if (! $persistent || ! $this->enabled() || isset($this->dirty[$key])) {
            return null;
        }

        try {
            $cached = $this->store()->get($this->fullKey($key));
        } catch (Throwable) {
            return null;
        }

        if ($cached !== null) {
            $this->request[$key] = $cached;
        }

        return $cached;
    }

    public function put(string $key, mixed $value, string $ttlType, bool $persistent = true): void
    {
        $this->request[$key] = $value;

        if (! $persistent || ! $this->enabled() || isset($this->dirty[$key])) {
            return;
        }

        try {
            $this->store()->put($this->fullKey($key), $value, $this->ttl($ttlType));
        } catch (Throwable) {
            // Cache write failures must never change the authorization outcome.
        }
    }

    public function forget(string $key): void
    {
        unset($this->request[$key]);
        $this->dirty[$key] = true;

        $this->afterCommit(function () use ($key) {
            try {
                $this->store()->forget($this->fullKey($key));
            } catch (Throwable) {
                // Cache store failures must never grant access.
            }

            unset($this->dirty[$key]);
        });
    }

    public function forgetUser(object $user): void
    {
        $userKey = $this->userKey($user);

        $this->forget("user:{$userKey}:roles");
        $this->forget("user:{$userKey}:permissions");
        $this->forget("user:{$userKey}:matrix");
        $this->bump("user:{$userKey}:generation");
    }

    public function forgetRole(string $slug): void
    {
        $this->forget("role:{$slug}");
        $this->forget("role:{$slug}:permissions");
        $this->forget("role:{$slug}:hierarchy");
        $this->forgetRegistry();
        $this->bump('generation');
    }

    public function forgetPermission(string $name): void
    {
        $this->forget("permission:{$name}");
        $this->forgetRegistry();
        $this->bump('generation');
    }

    public function generations(object $user): string
    {
        $global = (int) ($this->get('generation') ?? 0);
        $userGeneration = (int) ($this->get('user:'.$this->userKey($user).':generation') ?? 0);

        return "g{$global}:u{$userGeneration}";
    }

    public function forgetRegistry(?string $guard = null): void
    {
        if ($guard) {
            $this->forget("registry:{$guard}");

            return;
        }

        foreach ($this->requestKeysStartingWith('registry:') as $key) {
            $this->forget($key);
        }

        $this->forget('registry');
    }

    public function flushRequestCache(): void
    {
        $this->request = [];
        $this->dirty = [];
        $this->computing = [];
    }

    public function clear(): void
    {
        $this->bump('generation');
        $this->forgetRegistry();
        $this->flushRequestCache();
    }

    public function userKey(object $user): string
    {
        $type = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;
        $id = method_exists($user, 'getKey') ? $user->getKey() : spl_object_id($user);

        return $type.':'.$id;
    }

    public function prefix(): string
    {
        return rtrim((string) config('permission.cache.prefix', 'libinkk:permission:v1'), ':');
    }

    protected function enabled(): bool
    {
        return (bool) config('permission.cache.enabled', true);
    }

    protected function fullKey(string $key): string
    {
        return $this->prefix().':'.$key;
    }

    protected function ttl(string $type): int
    {
        return (int) config("permission.cache.ttl.{$type}", config('permission.cache.ttl.permissions', 3600));
    }

    protected function store(): Repository
    {
        $store = config('permission.cache.store');

        if (is_string($store) && $store !== '') {
            return Cache::store($store);
        }

        return Cache::store();
    }

    protected function withLock(string $fullKey, Closure $callback): mixed
    {
        if (! config('permission.cache.lock.enabled', true)) {
            return $callback();
        }

        try {
            $lock = $this->store()->lock($fullKey.':lock', (int) config('permission.cache.lock.seconds', 10));

            return $lock->block((int) config('permission.cache.lock.wait', 5), $callback);
        } catch (LockTimeoutException) {
            return $callback();
        } catch (Throwable) {
            return $callback();
        }
    }

    protected function afterCommit(Closure $callback): void
    {
        DB::afterCommit($callback);
    }

    protected function bump(string $key): int
    {
        $next = (int) ($this->get($key) ?? 0) + 1;
        $this->request[$key] = $next;
        $this->dirty[$key] = true;

        $this->afterCommit(function () use ($key, $next) {
            try {
                $this->store()->put($this->fullKey($key), $next, $this->ttl('permissions'));
            } catch (Throwable) {
                // Cache store failures must never grant access.
            }

            unset($this->dirty[$key]);
        });

        return $next;
    }

    /**
     * @return list<string>
     */
    protected function requestKeysStartingWith(string $prefix): array
    {
        return array_values(array_filter(
            array_keys($this->request),
            fn (string $key) => str_starts_with($key, $prefix)
        ));
    }
}
