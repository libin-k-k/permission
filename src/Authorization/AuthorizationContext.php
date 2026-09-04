<?php

namespace Libinkk\Permission\Authorization;

final class AuthorizationContext
{
    public function __construct(
        public object $user,
        public string $permission,
        public string $guard,
        public mixed $resource = null,
        public array $arguments = [],
    ) {
    }

    public function hasResource(): bool
    {
        return $this->resource !== null;
    }

    public function userKey(): string
    {
        $type = method_exists($this->user, 'getMorphClass')
            ? $this->user->getMorphClass()
            : $this->user::class;

        $id = method_exists($this->user, 'getKey')
            ? $this->user->getKey()
            : spl_object_id($this->user);

        return $type.':'.$id;
    }

    public function resourceKey(): ?string
    {
        if (! is_object($this->resource)) {
            return $this->resource === null ? null : (string) $this->resource;
        }

        $type = $this->resource::class;
        $id = method_exists($this->resource, 'getKey') ? $this->resource->getKey() : spl_object_id($this->resource);

        return $type.':'.$id;
    }
}
