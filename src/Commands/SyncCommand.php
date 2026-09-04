<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Discovery\PermissionDiscovery;

class SyncCommand extends Command
{
    protected $signature = 'permission:sync
                            {--path=* : Extra paths to scan}
                            {--dry-run : Show what would be created without writing}';

    protected $description = 'Discover permissions from attributes and sync missing ones to the database';

    public function handle(PermissionDiscovery $discovery): int
    {
        $paths = array_values(array_unique(array_filter(array_merge(
            $discovery->paths(),
            (array) $this->option('path')
        ))));

        $result = $discovery->sync($paths, (bool) $this->option('dry-run'));

        $this->components->info("Discovered {$result['discovered']} permission(s).");

        if ($result['created'] === []) {
            $this->components->twoColumnDetail('Created', '0');
        } else {
            $label = $this->option('dry-run') ? 'Would create' : 'Created';
            $this->components->info("{$label} ".count($result['created']).':');
            foreach ($result['created'] as $name) {
                $this->line('  + '.$name);
            }
        }

        $this->components->twoColumnDetail('Already present', (string) count($result['existing']));

        return self::SUCCESS;
    }
}
