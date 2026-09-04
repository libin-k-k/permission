<?php

namespace Libinkk\Permission\Filament;

use Closure;
use Illuminate\Http\Request;
use Libinkk\Permission\Authorization\AuthorizationContext;

class SyncFilamentTenantContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        $this->sync();

        return $next($request);
    }

    public function sync(): void
    {
        if (! config('permission.filament.enabled', false) || ! config('permission.filament.sync_tenant', true)) {
            return;
        }

        $tenant = $this->currentTenant();

        if ($tenant === null) {
            return;
        }

        AuthorizationContext::tenant($tenant);
    }

    public function currentTenant(): mixed
    {
        $facade = 'Filament\\Facades\\Filament';

        if (! class_exists($facade) || ! is_callable([$facade, 'getTenant'])) {
            return null;
        }

        try {
            return $facade::getTenant();
        } catch (\Throwable) {
            return null;
        }
    }
}
