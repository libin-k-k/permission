<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'permission:install
                            {--migrate : Run package migrations}
                            {--frontend : Publish Vue / React helpers}
                            {--force : Overwrite existing config}';

    protected $description = 'Publish libinkk/permission config and optionally migrate';

    public function handle(): int
    {
        $this->components->info('Installing libinkk/permission...');

        Artisan::call('vendor:publish', [
            '--tag' => 'libinkk-permission-config',
            '--force' => (bool) $this->option('force'),
        ], $this->output);

        if ($this->option('frontend')) {
            Artisan::call('vendor:publish', [
                '--tag' => 'libinkk-permission-frontend',
                '--force' => (bool) $this->option('force'),
            ], $this->output);
        }

        if ($this->option('migrate')) {
            Artisan::call('migrate', [], $this->output);
        } else {
            $this->components->twoColumnDetail('Next', 'php artisan migrate');
        }

        $this->components->success('libinkk/permission installed.');

        return self::SUCCESS;
    }
}
