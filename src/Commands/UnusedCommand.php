<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Debug\UnusedPermissionFinder;

class UnusedCommand extends Command
{
    protected $signature = 'permission:unused
                            {--guard= : Guard to inspect}
                            {--json : Output JSON}';

    protected $description = 'List unused, inactive, or unreferenced permissions';

    public function handle(UnusedPermissionFinder $finder): int
    {
        $result = $finder->find($this->option('guard') ?: null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Unused Permissions');
        $this->newLine();

        $this->section('Never assigned', $result['unassigned']);
        $this->section('Inactive', $result['inactive']);
        $this->section('Not discovered in code', $result['not_in_code']);
        $this->section('Assigned but no active users', $result['assigned_without_users']);

        $this->newLine();
        $this->components->twoColumnDetail('Total unique', (string) $result['total']);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $names
     */
    protected function section(string $title, array $names): void
    {
        $this->components->info($title);

        if ($names === []) {
            $this->line('  (none)');

            return;
        }

        foreach ($names as $name) {
            $this->line('  - '.$name);
        }
    }
}
