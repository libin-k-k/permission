<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Commands\Concerns\RendersConsoleStatus;
use Libinkk\Permission\Contracts\PermissionCache;

class ClearCacheCommand extends Command
{
    use RendersConsoleStatus;

    protected $signature = 'permission:cache:clear';

    protected $description = 'Clear libinkk/permission caches';

    public function handle(PermissionCache $cache): int
    {
        $cache->clear();

        $this->successStatus('Permission cache cleared.');

        return self::SUCCESS;
    }
}
