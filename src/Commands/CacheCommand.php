<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Cache\PermissionPreloader;
use Libinkk\Permission\Commands\Concerns\RendersConsoleStatus;

class CacheCommand extends Command
{
    use RendersConsoleStatus;

    protected $signature = 'permission:cache
                            {--guard= : Guard to warm}';

    protected $description = 'Warm permission, role, and hierarchy caches';

    public function handle(PermissionPreloader $preloader): int
    {
        $result = $preloader->warmGuard($this->option('guard') ?: null);

        $this->successStatus(
            "Warmed cache for guard [{$result['guard']}] ({$result['roles']} roles, {$result['permissions']} permissions)."
        );

        return self::SUCCESS;
    }
}
