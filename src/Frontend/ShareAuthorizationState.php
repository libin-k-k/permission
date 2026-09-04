<?php

namespace Libinkk\Permission\Frontend;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ShareAuthorizationState
{
    public function __construct(
        protected FrontendPayload $payload,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('permission.frontend.enabled', false) || ! config('permission.frontend.share', false)) {
            return $next($request);
        }

        $state = $this->payload->for($request->user());

        View::share('authorization', $state);

        $inertia = 'Inertia\\Inertia';

        if (class_exists($inertia) && is_callable([$inertia, 'share'])) {
            $inertia::share('authorization', $state);
        }

        return $next($request);
    }
}
