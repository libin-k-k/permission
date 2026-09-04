<?php

namespace Libinkk\Permission\Filament;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FilamentAuthorization
{
    public static function user(): ?object
    {
        $user = Auth::user();

        return is_object($user) ? $user : null;
    }

    public static function allows(string $permission, mixed $record = null): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        if (! method_exists($user, 'can')) {
            return false;
        }

        return $record === null
            ? (bool) $user->can($permission)
            : (bool) $user->can($permission, $record);
    }

    /**
     * Map a Filament ability (viewAny, create, attach, …) onto resource.action.
     */
    public static function permission(string $resource, string $ability): string
    {
        $map = config('permission.filament.actions', []);
        $action = $map[$ability] ?? Str::snake($ability, '-');

        return $resource.'.'.$action;
    }

    /**
     * Guess a permission resource slug from a Filament class name.
     */
    public static function guessResource(string $class): string
    {
        $name = class_basename($class);
        $name = (string) preg_replace('/(Resource|RelationManager|Widget|Page)$/', '', $name);

        return (string) Str::of($name)->kebab()->plural();
    }

    /**
     * @param  iterable<mixed>  $records
     */
    public static function bulk(string $permission, iterable $records, ?string $mode = null): bool
    {
        $result = self::bulkBreakdown($permission, $records);
        $mode ??= (string) config('permission.filament.bulk', 'all');

        if ($result['total'] === 0) {
            return false;
        }

        return $mode === 'any' ? $result['allowed'] > 0 : $result['denied'] === 0;
    }

    /**
     * @param  iterable<mixed>  $records
     * @return array{allowed: int, denied: int, total: int, partial: bool, records: list<array{record: mixed, allowed: bool}>}
     */
    public static function bulkBreakdown(string $permission, iterable $records): array
    {
        $rows = [];
        $allowed = 0;
        $denied = 0;

        foreach ($records as $record) {
            $ok = self::allows($permission, $record);
            $rows[] = ['record' => $record, 'allowed' => $ok];
            $ok ? $allowed++ : $denied++;
        }

        $total = $allowed + $denied;

        return [
            'allowed' => $allowed,
            'denied' => $denied,
            'total' => $total,
            'partial' => $allowed > 0 && $denied > 0,
            'records' => $rows,
        ];
    }

    /**
     * Authorized subset of a bulk selection.
     *
     * @param  iterable<mixed>  $records
     */
    public static function authorizedRecords(string $permission, iterable $records): Collection
    {
        return Collection::make($records)
            ->filter(fn (mixed $record) => self::allows($permission, $record))
            ->values();
    }

    public static function visible(string $permission, mixed $record = null): Closure
    {
        return fn (mixed ...$arguments) => self::allows($permission, $record ?? self::recordFromArguments($arguments));
    }

    public static function hidden(string $permission, mixed $record = null): Closure
    {
        return fn (mixed ...$arguments) => ! self::allows($permission, $record ?? self::recordFromArguments($arguments));
    }

    public static function disabled(string $permission, mixed $record = null): Closure
    {
        return self::hidden($permission, $record);
    }

    public static function bulkCallback(string $permission, ?string $mode = null): Closure
    {
        return function (mixed $records = null) use ($permission, $mode) {
            return self::bulk($permission, is_iterable($records) ? $records : [], $mode);
        };
    }

    public static function navigation(string $permission): Closure
    {
        return fn () => self::allows($permission);
    }

    /**
     * @param  list<mixed>  $arguments
     */
    protected static function recordFromArguments(array $arguments): mixed
    {
        foreach ($arguments as $argument) {
            if (is_object($argument) && method_exists($argument, 'getKey')) {
                return $argument;
            }
        }

        return null;
    }
}
