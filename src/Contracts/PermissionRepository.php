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
    public function directPermissionsFor(object $user, string $guard): array;

    /**
     * @return list<array{name: string, effect: string}>
     */
    public function directPermissionEntriesFor(object $user, string $guard): array;

    public function findOrCreate(string $name, string $guard): array;
}
