<?php

namespace Libinkk\Permission\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthorizationApiController extends Controller
{
    public function __construct(
        protected FrontendPayload $payload,
        protected PermissionMatrix $matrix,
    ) {
    }

    public function authorization(Request $request): JsonResponse
    {
        $this->guardEnabled();

        $user = $request->user();

        if (! is_object($user)) {
            abort(401);
        }

        $body = $this->payload->for($user);

        return response()->json([
            'user' => $body['user'],
            'roles' => $body['roles'],
            'permissions' => $body['permissions'],
            'denials' => $body['denials'],
            'scopes' => $body['scopes'],
            'resources' => $body['resources'],
        ]);
    }

    public function access(Request $request, mixed $user): JsonResponse
    {
        $this->guardEnabled();

        $actor = $request->user();

        if (! is_object($actor)) {
            abort(401);
        }

        $target = $this->resolveUser($user);

        if (! $this->canViewAccess($actor, $target)) {
            throw new AccessDeniedHttpException('Cannot view another user\'s authorization payload.');
        }

        return response()->json($this->payload->access($target));
    }

    public function matrix(Request $request): JsonResponse
    {
        $this->guardEnabled();

        if (! is_object($request->user())) {
            abort(401);
        }

        return response()->json($this->matrix->all());
    }

    protected function guardEnabled(): void
    {
        if (! config('permission.frontend.enabled', false)) {
            throw new NotFoundHttpException;
        }
    }

    protected function resolveUser(mixed $user): object
    {
        if (is_object($user) && method_exists($user, 'getKey')) {
            return $user;
        }

        $class = (string) config('permission.models.user');

        if (! class_exists($class)) {
            throw new NotFoundHttpException;
        }

        $model = $class::query()->find($user);

        if (! $model) {
            throw new NotFoundHttpException;
        }

        return $model;
    }

    protected function canViewAccess(object $actor, object $target): bool
    {
        if (! method_exists($actor, 'getKey') || ! method_exists($target, 'getKey')) {
            return false;
        }

        $sameType = (! method_exists($actor, 'getMorphClass') || ! method_exists($target, 'getMorphClass'))
            || $actor->getMorphClass() === $target->getMorphClass();

        if ($sameType && (string) $actor->getKey() === (string) $target->getKey()) {
            return true;
        }

        $ability = config('permission.frontend.access_user_permission');

        if (! is_string($ability) || $ability === '') {
            return false;
        }

        return method_exists($actor, 'can') && $actor->can($ability);
    }
}
