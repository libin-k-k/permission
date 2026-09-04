<?php

use Illuminate\Support\Facades\Auth;
use Libinkk\Permission\Frontend\FrontendPayload;

if (! function_exists('permission_payload')) {
    /**
     * Frontend-safe authorization payload for the current (or given) user.
     * UI-only — never treat this as a security boundary.
     *
     * @return array<string, mixed>
     */
    function permission_payload(?object $user = null): array
    {
        $user ??= Auth::user();

        return app(FrontendPayload::class)->for(is_object($user) ? $user : null);
    }
}
