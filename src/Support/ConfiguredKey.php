<?php

namespace Libinkk\Permission\Support;

use Illuminate\Support\Str;

class ConfiguredKey
{
    public static function new(): ?string
    {
        return match (config('permission.database.primary_key', 'bigint')) {
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function withId(array $row): array
    {
        $id = self::new();

        if ($id !== null && ! isset($row['id'])) {
            $row['id'] = $id;
        }

        return $row;
    }
}
