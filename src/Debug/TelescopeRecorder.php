<?php

namespace Libinkk\Permission\Debug;

use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Events\AuthorizationAllowed;
use Libinkk\Permission\Events\AuthorizationDenied;

class TelescopeRecorder
{
    public function handleAllowed(AuthorizationAllowed $event): void
    {
        $this->record($event->decision);
    }

    public function handleDenied(AuthorizationDenied $event): void
    {
        $this->record($event->decision);
    }

    public function record(Decision $decision): void
    {
        if (! config('permission.debug.enabled', false) || ! config('permission.debug.telescope', true)) {
            return;
        }

        $telescope = 'Laravel\\Telescope\\Telescope';
        $incoming = 'Laravel\\Telescope\\IncomingEntry';

        if (! class_exists($telescope) || ! class_exists($incoming)) {
            return;
        }

        if (method_exists($telescope, 'isRecording') && ! $telescope::isRecording()) {
            return;
        }

        $entry = $incoming::make([
            'ability' => $decision->permission,
            'result' => $decision->allowed() ? 'allowed' : 'denied',
            'reason' => $decision->reason,
            'source' => $decision->source,
            'decision' => $decision->toArray(),
        ]);

        if (is_object($entry) && method_exists($entry, 'tags')) {
            $entry->tags(['libinkk-permission']);
        }

        if (method_exists($telescope, 'recordGate')) {
            $telescope::recordGate($entry);

            return;
        }

        if (method_exists($telescope, 'record')) {
            $telescope::record($entry);
        }
    }
}
