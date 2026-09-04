<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Permissions\Permission;

class ResourceCommand extends Command
{
    protected $signature = 'permission:resource
                            {name : Resource name, e.g. posts}
                            {--actions= : Comma-separated actions (default: CRUD)}
                            {--group= : Permission group label}
                            {--guard= : Guard name}
                            {--crud : Create view/create/update/delete only}';

    protected $description = 'Create permissions for a resource';

    public function handle(): int
    {
        $resource = (string) $this->argument('name');
        $guard = $this->option('guard') ?: config('permission.default_guard', 'web');
        $group = $this->option('group') ?: null;

        $attributes = array_filter([
            'guard' => $guard,
            'group' => $group,
        ], fn ($value) => $value !== null && $value !== '');

        if ($this->option('actions')) {
            $actions = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('actions')))));
            $created = Permission::defineResource($resource, $actions, $attributes);
        } else {
            $created = Permission::crud($resource, $attributes);
        }

        $this->components->info("Created {$created->count()} permissions for [{$resource}]:");

        foreach ($created as $permission) {
            $this->line('  - '.$permission->name.($permission->group ? " ({$permission->group})" : ''));
        }

        return self::SUCCESS;
    }
}
