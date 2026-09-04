<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Cache\PermissionPreloader;

class CacheCommand extends Command
{
    protected $signature = 'permission:cache
                            {--guard= : Guard to warm}';

    protected $description = 'Warm permission, role, and hierarchy caches';

    public function handle(PermissionPreloader $preloader): int
    {
        $result = $preloader->warmGuard($this->option('guard') ?: null);

        $this->components->success(
            "Warmed cache for guard [{$result['guard']}] ({$result['roles']} roles, {$result['permissions']} permissions)."
        );

        return self::SUCCESS;
    }
}
