<?php

namespace Libinkk\Permission\Cache;

class PermissionFake
{
    protected static ?self $instance = null;

    /**
     * @var array<string, bool>
     */
    protected array $permissions = [];

    public static function activate(): self
    {
        if (function_exists('app') && app()->bound('env') && app()->environment('production')
            && ! config('permission.testing.allow_fake', false)) {
            throw new \RuntimeException('Permission::fake() is disabled in production.');
        }

        return self::$instance ??= new self;
    }

    public static function instance(): self
    {
        return self::activate();
    }

    public static function isActive(): bool
    {
        return self::$instance !== null;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function allow(string $permission): void
    {
        $this->permissions[$permission] = true;
    }

    public function deny(string $permission): void
    {
        $this->permissions[$permission] = false;
    }

    public function has(string $permission): bool
    {
        return array_key_exists($permission, $this->permissions);
    }

    public function allowed(string $permission): bool
    {
        return ($this->permissions[$permission] ?? false) === true;
    }
}
