<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Authorization\UserAccessExporter;

class ExportUserAccessCommand extends Command
{
    protected $signature = 'permission:export
                            {user : User ID}
                            {--type= : User morph / model class (defaults to configured user model)}
                            {--guard= : Authorization guard}
                            {--format=json : Output format: json, table, or summary}
                            {--path= : Write JSON export to a file path}';

    protected $description = 'Export a user\'s total roles and permissions (sources, groups, totals)';

    public function handle(UserAccessExporter $exporter): int
    {
        $type = $this->option('type') ?: config('permission.models.user');

        if (! is_string($type) || ! class_exists($type)) {
            $this->components->error('User model class not found. Pass --type=App\\Models\\User');

            return self::FAILURE;
        }

        $user = $type::query()->find($this->argument('user'));

        if (! $user) {
            $this->components->error("User [{$this->argument('user')}] not found on [{$type}].");

            return self::FAILURE;
        }

        $export = $exporter->export($user, $this->option('guard') ?: null);
        $format = strtolower((string) $this->option('format'));

        if ($path = $this->option('path')) {
            file_put_contents($path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->components->success("Wrote access export to [{$path}].");
        }

        if ($format === 'json' && ! $this->option('path')) {
            $this->line(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderSummary($export);

        if ($format === 'table') {
            $this->newLine();
            $this->components->info('Roles');
            $this->table(
                ['Slug', 'Name', 'Priority', 'Permissions'],
                collect($export['roles'])->map(fn (array $role) => [
                    $role['slug'],
                    $role['name'],
                    $role['priority'],
                    $role['permission_count'],
                ])->all()
            );

            $this->newLine();
            $this->components->info('Effective permissions');
            $this->table(
                ['Permission', 'Source', 'Via', 'Group'],
                collect($export['effective_permissions'])->map(fn (array $meta, string $name) => [
                    $name,
                    $meta['source'],
                    $meta['via'],
                    $meta['group'] ?? '',
                ])->values()->all()
            );
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $export
     */
    protected function renderSummary(array $export): void
    {
        $totals = $export['totals'];

        $this->newLine();
        $this->components->info('User Access Export');
        $this->components->twoColumnDetail('User', $export['user']['type'].'#'.$export['user']['id']);
        $this->components->twoColumnDetail('Guard', $export['guard']);
        $this->components->twoColumnDetail('Exported at', $export['exported_at']);
        $this->newLine();
        $this->components->twoColumnDetail('Roles', (string) $totals['roles']);
        $this->components->twoColumnDetail('Direct permissions', (string) $totals['direct_permissions']);
        $this->components->twoColumnDetail('Assigned permissions', (string) $totals['assigned_permissions']);
        $this->components->twoColumnDetail('Effective permissions', (string) $totals['effective_permissions']);
        $this->components->twoColumnDetail('Groups', (string) $totals['groups']);
        $this->components->twoColumnDetail('Resources', (string) $totals['resources']);
    }
}
