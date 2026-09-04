<?php

namespace Libinkk\Permission\Debug;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthorizationDebugController extends Controller
{
    public function __construct(
        protected AuthorizationDebugger $debugger,
    ) {
    }

    public function explain(Request $request): JsonResponse
    {
        if (! config('permission.debug.enabled', false) || ! config('permission.debug.routes', true)) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();

        if (! is_object($user)) {
            abort(401);
        }

        $permission = trim((string) $request->query('permission', ''));

        if ($permission === '' || str_contains($permission, "\0")) {
            return response()->json([
                'message' => 'The permission query parameter is required.',
            ], 422);
        }

        $report = $this->debugger->debug($user, $permission);

        return response()->json($report);
    }
}
