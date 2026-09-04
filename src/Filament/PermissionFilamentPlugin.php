<?php

namespace Libinkk\Permission\Filament;

/**
 * Optional Filament panel hook. Does not implement Filament's Plugin
 * interface so this package never depends on Filament types.
 *
 * In a panel provider:
 *
 *     $panel->middleware(PermissionFilamentPlugin::middleware());
 *
 * Or register ServingFilament via the service provider (automatic when
 * permission.filament.enabled is true and Filament is installed).
 */
class PermissionFilamentPlugin
{
    public static function make(): self
    {
        return new self;
    }

    /**
     * @return list<class-string>
     */
    public static function middleware(): array
    {
        return [SyncFilamentTenantContext::class];
    }

    /**
     * Duck-typed Filament plugin id when the host app wraps this object.
     */
    public function getId(): string
    {
        return 'libinkk-permission';
    }

    public function register(mixed $panel): void
    {
        if (is_object($panel) && method_exists($panel, 'middleware')) {
            $panel->middleware(self::middleware());
        }
    }

    public function boot(mixed $panel): void
    {
        app(SyncFilamentTenantContext::class)->sync();
    }
}
