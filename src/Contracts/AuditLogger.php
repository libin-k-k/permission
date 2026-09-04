<?php

namespace Libinkk\Permission\Contracts;

interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $event, array $payload = []): void;
}
