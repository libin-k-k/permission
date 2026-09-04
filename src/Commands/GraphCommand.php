<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Debug\PermissionGraph;

class GraphCommand extends Command
{
    protected $signature = 'permission:graph
                            {--guard= : Guard to inspect}
                            {--json : Output JSON}';

    protected $description = 'Print the role inheritance and permission graph';

    public function handle(PermissionGraph $graph): int
    {
        $built = $graph->build($this->option('guard') ?: null);

        if ($this->option('json')) {
            $this->line($graph->toJson($built));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Permission Graph');
        $this->newLine();
        $this->line($graph->toText($built));

        return self::SUCCESS;
    }
}
