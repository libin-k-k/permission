<?php

namespace Libinkk\Permission\Contracts;

interface RoleRepository
{
    public function findByNameOrSlug(string $name, string $guard): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function assignedRoles(object $user, string $guard): array;

    /**
     * @return list<string>
     */
    public function permissionNamesForRole(int|string $roleId): array;

    public function findOrCreate(string $name, string $guard): array;
}
