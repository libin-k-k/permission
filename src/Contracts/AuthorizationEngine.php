<?php

namespace Libinkk\Permission\Contracts;

interface AuthorizationEngine
{
    public function manages(string $ability): bool;

    public function decide(object $user, string $permission, array $arguments = []): \Libinkk\Permission\Authorization\Decision;

    public function allows(object $user, string $permission, array $arguments = []): bool;
}
