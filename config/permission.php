<?php

return [

    'enabled' => true,

    'default_guard' => 'web',

    'models' => [
        'user' => env('AUTH_MODEL', Illuminate\Foundation\Auth\User::class),
        'role' => Libinkk\Permission\Roles\Role::class,
        'permission' => Libinkk\Permission\Permissions\Permission::class,
        'scope' => Libinkk\Permission\Scopes\Scope::class,
        'tenant' => Libinkk\Permission\Scopes\Tenant::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        'user_roles' => 'user_roles',
        'user_permissions' => 'user_permissions',
        'role_inheritances' => 'role_inheritances',
        'permission_conditions' => 'permission_conditions',
        'permission_condition_values' => 'permission_condition_values',
        'scopes' => 'scopes',
        'role_scopes' => 'role_scopes',
        'permission_scopes' => 'permission_scopes',
        'user_scopes' => 'user_scopes',
        'tenants' => 'tenants',
        'tenant_users' => 'tenant_users',
        'permission_delegations' => 'permission_delegations',
        'permission_versions' => 'permission_versions',
        'authorization_audits' => 'authorization_audits',
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
            'delegations' => 1800,
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
        'redis' => [
            'enabled' => false,
            'store' => env('PERMISSION_CACHE_REDIS_STORE', 'redis'),
        ],
        'immediate_invalidation' => true,
        'metrics' => true,
    ],

    'middleware' => [
        'permission_logic' => 'or',
        'role_logic' => 'or',
    ],

    'teams' => [
        'enabled' => false,
        'require_context' => false,
        'require_membership' => false,
        'own_tenants' => false,
        'global_roles' => [
            'cross_tenant' => false,
        ],
    ],

    'scopes' => [
        'inherit' => true,
    ],

    'hierarchy' => [
        'enabled' => true,
    ],

    'deny' => [
        'enabled' => true,
        'precedence' => [
            'explicit_deny',
            'explicit_allow',
            'role_deny',
            'role_allow',
            'inherited_deny',
            'inherited_allow',
        ],
    ],

    'conditions' => [
        'enabled' => true,
        'persist_named' => true,
    ],

    'ownership' => [
        'auto_own_suffix' => true,
        'attribute' => null, // e.g. author_id; null = try common fields
    ],

    'delegation' => [
        'enabled' => true,
    ],

    'versioning' => [
        'enabled' => true,
    ],

    'audit' => [
        'enabled' => false,
        'decisions' => false,
    ],

    'frontend' => [
        'enabled' => false,
        'routes' => true,
        'prefix' => 'api',
        'middleware' => ['web'],
        'share' => false,
        'access_user_permission' => null, // e.g. users.access — required to view another user
    ],

    'debug' => [
        'enabled' => false,
        'routes' => true,
        'prefix' => 'api',
        'middleware' => ['web'],
        'telescope' => true,
        'debugbar' => true,
        'record_decisions' => false,
        'max_recorded' => 50,
    ],

    'filament' => [
        'enabled' => false,
        'sync_tenant' => true,
        'navigation' => true,
        'bulk' => 'all', // all = every selected record; any = partial allowed
        'actions' => [
            'viewAny' => 'view',
            'view' => 'view',
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'deleteAny' => 'delete',
            'restore' => 'restore',
            'restoreAny' => 'restore',
            'forceDelete' => 'force-delete',
            'forceDeleteAny' => 'force-delete',
            'replicate' => 'create',
            'attach' => 'attach',
            'detach' => 'detach',
            'associate' => 'associate',
            'dissociate' => 'dissociate',
        ],
    ],

    'testing' => [
        'allow_fake' => false,
    ],

    'discovery' => [
        'enabled' => true,
        'paths' => [
            // app_path('Http/Controllers'),
            // app_path('Actions'),
        ],
    ],

];
