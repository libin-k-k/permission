<?php

namespace Libinkk\Permission\Conditions;

final class OwnershipChecker
{
    /**
     * @param  list<string>|null  $attributes
     */
    public static function owns(object $user, mixed $resource, ?string $attribute = null, ?array $attributes = null): bool
    {
        if (! is_object($resource)) {
            return false;
        }

        if (method_exists($resource, 'isOwnedBy')) {
            return (bool) $resource->isOwnedBy($user);
        }

        $userId = method_exists($user, 'getKey') ? $user->getKey() : null;

        if ($userId === null) {
            return false;
        }

        $candidates = $attribute
            ? [$attribute]
            : ($attributes ?? ['user_id', 'author_id', 'owner_id', 'created_by', 'created_by_id']);

        foreach ($candidates as $field) {
            if (! isset($resource->{$field})) {
                continue;
            }

            if ((string) $resource->{$field} === (string) $userId) {
                return true;
            }
        }

        return false;
    }
}
