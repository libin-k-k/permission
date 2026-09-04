<?php

namespace Libinkk\Permission\Discovery;

use Illuminate\Support\Collection;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Permissions\Permission;

class PermissionDiscovery
{
    public function __construct(
        protected AttributeScanner $scanner,
        protected PermissionCache $cache,
    ) {
    }

    /**
     * @param  list<string>|null  $paths
     * @return Collection<int, array<string, mixed>>
     */
    public function discover(?array $paths = null): Collection
    {
        $paths ??= $this->paths();

        return collect($this->scanner->scan($paths))
            ->sortBy('name')
            ->values();
    }

    /**
     * Discover and persist missing permissions.
     *
     * @param  list<string>|null  $paths
     * @return array{created: list<string>, existing: list<string>, discovered: int}
     */
    public function sync(?array $paths = null, bool $dryRun = false): array
    {
        $discovered = $this->discover($paths);
        $created = [];
        $existing = [];

        foreach ($discovered as $item) {
            $permission = Permission::query()
                ->where('guard_name', $item['guard'])
                ->where(fn ($query) => $query->where('name', $item['name'])->orWhere('slug', $item['name']))
                ->first();

            if ($permission) {
                $existing[] = $item['name'];

                continue;
            }

            if (! $dryRun) {
                Permission::query()->create([
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'group' => $item['group'],
                    'resource' => $item['resource'],
                    'action' => $item['action'],
                    'guard_name' => $item['guard'],
                    'risk_level' => $item['risk_level'] ?? 'LOW',
                    'is_dangerous' => $item['is_dangerous'] ?? false,
                    'requires_audit' => $item['requires_audit'] ?? false,
                    'is_active' => true,
                ]);
            }

            $created[] = $item['name'];
        }

        if ($created !== [] && ! $dryRun) {
            $this->cache->forgetRegistry();
            $this->cache->flushRequestCache();
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'discovered' => $discovered->count(),
        ];
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        $configured = config('permission.discovery.paths', []);

        $defaults = [
            app_path('Http/Controllers'),
            app_path('Livewire'),
            app_path('Filament'),
            app_path('Actions'),
        ];

        return array_values(array_unique(array_filter(
            array_merge($defaults, is_array($configured) ? $configured : []),
            fn (string $path) => is_dir($path)
        )));
    }
}
