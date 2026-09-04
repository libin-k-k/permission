<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Contracts\PermissionCache;

class ClearCacheCommand extends Command
{
    protected $signature = 'permission:cache:clear';

    protected $description = 'Clear libinkk/permission caches';

    public function handle(PermissionCache $cache): int
    {
        $cache->clear();

        $this->components->success('Permission cache cleared.');

        return self::SUCCESS;
    }
}
