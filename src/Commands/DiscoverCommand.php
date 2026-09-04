<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Discovery\PermissionDiscovery;

class DiscoverCommand extends Command
{
    protected $signature = 'permission:discover
                            {--path=* : Extra paths to scan}
                            {--json : Output JSON}';

    protected $description = 'Discover permissions from PHP attributes in application code';

    public function handle(PermissionDiscovery $discovery): int
    {
        $paths = array_values(array_unique(array_filter(array_merge(
            $discovery->paths(),
            (array) $this->option('path')
        ))));

        $found = $discovery->discover($paths);

        if ($this->option('json')) {
            $this->line($found->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($found->isEmpty()) {
            $this->components->warn('No permissions discovered.');
            $this->line('Scanned: '.(empty($paths) ? '(no paths)' : implode(', ', $paths)));

            return self::SUCCESS;
        }

        $this->components->info("Discovered {$found->count()} permission(s):");

        $this->table(
            ['Name', 'Group', 'Guard', 'Source'],
            $found->map(fn (array $item) => [
                $item['name'],
                $item['group'] ?? '',
                $item['guard'],
                trim(($item['source_class'] ?? '').'::'.($item['source_method'] ?? ''), ':'),
            ])->all()
        );

        return self::SUCCESS;
    }
}
