<?php

namespace Libinkk\Permission\Contracts;

interface PermissionRepository
{
    public function findByName(string $name, string $guard): ?array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allActive(string $guard): array;

    /**
     * @return list<string>
     */
    public function directPermissionsFor(object $user, string $guard, array $scopePivots = []): array;

    /**
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     * @return list<array{name: string, effect: string}>
     */
    public function directPermissionEntriesFor(object $user, string $guard, array $scopePivots = []): array;

    public function findOrCreate(string $name, string $guard): array;
}
