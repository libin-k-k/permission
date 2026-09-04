<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Debug\AuthorizationDebugger;

class ExplainCommand extends Command
{
    protected $signature = 'permission:explain
                            {user : User ID}
                            {permission : Permission name}
                            {--type= : User model class (defaults to configured user model)}
                            {--json : Output JSON}';

    protected $description = 'Explain why a user is allowed or denied a permission';

    public function handle(AuthorizationDebugger $debugger): int
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

        $report = $debugger->debug($user, (string) $this->argument('permission'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['final'] === 'ALLOWED' ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line($report['text']);

        return $report['final'] === 'ALLOWED' ? self::SUCCESS : self::FAILURE;
    }
}
