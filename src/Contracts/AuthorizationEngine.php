<?php

namespace Libinkk\Permission\Contracts;

interface AuthorizationEngine
{
    public function manages(string $ability): bool;

    public function decide(object $user, string $permission, array $arguments = []): \Libinkk\Permission\Authorization\Decision;

    public function allows(object $user, string $permission, array $arguments = []): bool;

    /**
     * @param  iterable<mixed>  $resources
     * @return list<array{resource: mixed, allowed: bool, decision: \Libinkk\Permission\Authorization\Decision}>
     */
    public function decideMany(object $user, string $permission, iterable $resources): array;

    /**
     * @return array<string, bool>
     */
    public function permissionsFor(object $user, string $resource): array;
}
