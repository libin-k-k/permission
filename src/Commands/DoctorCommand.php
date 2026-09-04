<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Support\PermissionDoctor;

class DoctorCommand extends Command
{
    protected $signature = 'permission:doctor
                            {--guard= : Guard to inspect}
                            {--json : Output JSON}';

    protected $description = 'Run authorization health checks (doctor)';

    public function handle(PermissionDoctor $doctor): int
    {
        $result = $doctor->run($this->option('guard') ?: null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Authorization Doctor');
        $this->newLine();

        foreach ($result['checks'] as $check) {
            $icon = match ($check['status']) {
                'ok' => '✓',
                'warn' => '⚠',
                default => '✗',
            };

            $this->line(" {$icon}  {$check['label']}");
        }

        $this->newLine();

        if ($result['healthy']) {
            $this->components->success('Authorization system is healthy.');

            return self::SUCCESS;
        }

        $this->components->error('Authorization system needs attention.');

        return self::FAILURE;
    }
}
