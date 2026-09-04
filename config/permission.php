<?php

return [

    'enabled' => true,

    'default_guard' => 'web',

    'models' => [
        'user' => env('AUTH_MODEL', Illuminate\Foundation\Auth\User::class),
        'role' => Libinkk\Permission\Roles\Role::class,
        'permission' => Libinkk\Permission\Permissions\Permission::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        'user_roles' => 'user_roles',
        'user_permissions' => 'user_permissions',
    ],

    'database' => [
        'primary_key' => 'bigint',
        'user_key' => 'bigint',
    ],

    'cache' => [
        'enabled' => true,
        'store' => env('PERMISSION_CACHE_STORE'),
        'driver' => env('PERMISSION_CACHE_DRIVER', 'redis'),
        'prefix' => 'libinkk:permission:v1',
        'ttl' => [
            'permissions' => 86400,
            'roles' => 86400,
            'user_roles' => 3600,
            'user_permissions' => 3600,
            'scopes' => 1800,
            'decisions' => 300,
        ],
        'decision_cache' => [
            'enabled' => true,
        ],
        'tags' => [
            'enabled' => true,
        ],
        'lock' => [
            'enabled' => true,
            'seconds' => 10,
            'wait' => 5,
        ],
    ],

    'middleware' => [
        'permission_logic' => 'or',
        'role_logic' => 'or',
    ],

    'teams' => [
        'enabled' => false,
    ],

    'hierarchy' => [
        'enabled' => false,
    ],

    'deny' => [
        'enabled' => false,
    ],

    'audit' => [
        'enabled' => false,
    ],

    'frontend' => [
        'enabled' => false,
    ],

    'filament' => [
        'enabled' => false,
    ],

];
