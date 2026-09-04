<?php

namespace Libinkk\Permission\Debug;

use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Events\AuthorizationAllowed;
use Libinkk\Permission\Events\AuthorizationDenied;

class DecisionRecorder
{
    /** @var list<array<string, mixed>> */
    protected array $entries = [];

    public function record(Decision $decision): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->entries[] = $decision->toArray();

        $max = max(1, (int) config('permission.debug.max_recorded', 50));

        if (count($this->entries) > $max) {
            $this->entries = array_values(array_slice($this->entries, -$max));
        }
    }

    public function handleAllowed(AuthorizationAllowed $event): void
    {
        $this->record($event->decision);
    }

    public function handleDenied(AuthorizationDenied $event): void
    {
        $this->record($event->decision);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function flush(): void
    {
        $this->entries = [];
    }

    protected function enabled(): bool
    {
        if ((bool) config('permission.debug.record_decisions', false)) {
            return true;
        }

        return (bool) config('permission.debug.enabled', false)
            && (bool) config('permission.debug.debugbar', false);
    }
}
