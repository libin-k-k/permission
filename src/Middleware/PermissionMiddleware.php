<?php

namespace Libinkk\Permission\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissions, ?string $guard = null): Response
    {
        $user = $request->user($guard);

        if (! $user) {
            abort(403, 'Unauthenticated.');
        }

        $names = $this->parse($permissions);
        $logic = strtolower((string) config('permission.middleware.permission_logic', 'or'));
        $allowed = $logic === 'and'
            ? collect($names)->every(fn (string $permission) => $user->can($permission))
            : collect($names)->contains(fn (string $permission) => $user->can($permission));

        if (! $allowed) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    protected function parse(string $permissions): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $permissions))));
    }
}
