<?php

namespace Libinkk\Permission\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles, ?string $guard = null): Response
    {
        $user = $request->user($guard);

        if (! $user || ! method_exists($user, 'hasRole')) {
            abort(403, 'Unauthenticated.');
        }

        $names = $this->parse($roles);
        $logic = strtolower((string) config('permission.middleware.role_logic', 'or'));
        $allowed = $logic === 'and'
            ? $user->hasAllRoles($names)
            : $user->hasAnyRole($names);

        if (! $allowed) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    protected function parse(string $roles): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $roles))));
    }
}
