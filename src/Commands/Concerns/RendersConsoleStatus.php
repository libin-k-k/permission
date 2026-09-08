<?php

namespace Libinkk\Permission\Commands\Concerns;

/**
 * Laravel 10 has no console Success component; Laravel 11+ does.
 */
trait RendersConsoleStatus
{
    protected function successStatus(string $message): void
    {
        if (class_exists(\Illuminate\Console\View\Components\Success::class)) {
            $this->components->success($message);

            return;
        }

        $this->components->info($message);
    }
}
