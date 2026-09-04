<?php

namespace Libinkk\Permission\Contracts;

interface RoleRepository
{
    public function findByNameOrSlug(string $name, string $guard): ?array;

    /**
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     * @return list<array<string, mixed>>
     */
    public function assignedRoles(object $user, string $guard, array $scopePivots = []): array;

    /**
     * @return list<string>
     */
    public function permissionNamesForRole(int|string $roleId): array;

    /**
     * @return list<array{name: string, effect: string}>
     */
    public function permissionEntriesForRole(int|string $roleId): array;

    public function findOrCreate(string $name, string $guard): array;
}
