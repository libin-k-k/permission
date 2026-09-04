<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Support\PermissionValidator;

class ValidateCommand extends Command
{
    protected $signature = 'permission:validate
                            {--guard= : Limit validation to a guard}
                            {--json : Output JSON}';

    protected $description = 'Validate roles, permissions, and assignments for integrity issues';

    public function handle(PermissionValidator $validator): int
    {
        $result = $validator->validate($this->option('guard') ?: null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($result['errors'] as $error) {
            $this->components->error($error['message']);
        }

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning['message']);
        }

        if ($result['ok']) {
            $this->components->success('Permission validation passed.');

            return self::SUCCESS;
        }

        $this->components->error('Permission validation failed with '.count($result['errors']).' error(s).');

        return self::FAILURE;
    }
}
