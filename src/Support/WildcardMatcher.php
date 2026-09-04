<?php

namespace Libinkk\Permission\Support;

final class WildcardMatcher
{
    public static function isWildcard(string $permission): bool
    {
        return str_contains($permission, '*');
    }

    /**
     * Whether a stored permission pattern grants the requested ability.
     *
     * Examples:
     * - posts.* matches posts.view
     * - posts.view.* matches posts.view.own
     * - * matches anything
     */
    public static function matches(string $pattern, string $permission): bool
    {
        if ($pattern === $permission) {
            return true;
        }

        if (! self::isWildcard($pattern)) {
            return false;
        }

        $regex = '/^'.str_replace('\*', '[^.]+', preg_quote($pattern, '/')).'$/';

        // posts.* should match posts.view and posts.view.own (one or more dotted segments)
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            $regex = '/^'.preg_quote($prefix, '/').'(\.[^.]+)+$/';
        } elseif ($pattern === '*') {
            $regex = '/^.+$/';
        }

        return (bool) preg_match($regex, $permission);
    }

    /**
     * @param  iterable<string>  $patterns
     */
    public static function firstMatch(iterable $patterns, string $permission): ?string
    {
        foreach ($patterns as $pattern) {
            if (self::matches((string) $pattern, $permission)) {
                return (string) $pattern;
            }
        }

        return null;
    }
}
