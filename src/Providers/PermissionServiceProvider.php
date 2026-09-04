<?php

namespace Libinkk\Permission\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Libinkk\Permission\Audit\AuditLogger;
use Libinkk\Permission\Authorization\AuthorizationEngine;
use Libinkk\Permission\Authorization\ExpirationChecker;
use Libinkk\Permission\Authorization\UserAccessExporter;
use Libinkk\Permission\Cache\DecisionCache;
use Libinkk\Permission\Cache\PermissionCache;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Commands\CacheCommand;
use Libinkk\Permission\Commands\ClearCacheCommand;
use Libinkk\Permission\Commands\DiscoverCommand;
use Libinkk\Permission\Commands\DoctorCommand;
use Libinkk\Permission\Commands\ExportUserAccessCommand;
use Libinkk\Permission\Commands\InstallCommand;
use Libinkk\Permission\Commands\ResourceCommand;
use Libinkk\Permission\Commands\SyncCommand;
use Libinkk\Permission\Commands\ValidateCommand;
use Libinkk\Permission\Conditions\Condition;
use Libinkk\Permission\Conditions\ConditionRegistry;
use Libinkk\Permission\Conditions\ConditionResolver;
use Libinkk\Permission\Conditions\OwnershipChecker;
use Libinkk\Permission\Contracts\AuditLogger as AuditLoggerContract;
use Libinkk\Permission\Contracts\AuthorizationEngine as AuthorizationEngineContract;
use Libinkk\Permission\Contracts\PermissionCache as PermissionCacheContract;
use Libinkk\Permission\Contracts\PermissionRepository as PermissionRepositoryContract;
use Libinkk\Permission\Contracts\RoleRepository as RoleRepositoryContract;
use Libinkk\Permission\Delegation\DelegationManager;
use Libinkk\Permission\Frontend\FrontendPayload;
use Libinkk\Permission\Frontend\PermissionMatrix;
use Libinkk\Permission\Events\AuthorizationAllowed;
use Libinkk\Permission\Events\AuthorizationDenied;
use Libinkk\Permission\Events\DelegationCreated;
use Libinkk\Permission\Events\DelegationRevoked;
use Libinkk\Permission\Events\PermissionGranted;
use Libinkk\Permission\Events\PermissionRevoked;
use Libinkk\Permission\Events\PolicyChanged;
use Libinkk\Permission\Events\RoleAssigned;
use Libinkk\Permission\Events\RoleRemoved;
use Libinkk\Permission\Discovery\AttributeScanner;
use Libinkk\Permission\Discovery\PermissionDiscovery;
use Libinkk\Permission\Middleware\PermissionMiddleware;
use Libinkk\Permission\Middleware\RoleMiddleware;
use Libinkk\Permission\Permissions\PermissionHistory;
use Libinkk\Permission\Permissions\PermissionManager;
use Libinkk\Permission\Permissions\PermissionRegistry;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Permissions\PermissionVersioner;
use Libinkk\Permission\Repositories\EloquentPermissionRepository;
use Libinkk\Permission\Repositories\EloquentRoleRepository;
use Libinkk\Permission\Roles\RoleHierarchy;
use Libinkk\Permission\Roles\RoleManager;
use Libinkk\Permission\Scopes\ScopeHierarchy;
use Libinkk\Permission\Scopes\ScopeResolver;
use Libinkk\Permission\Support\PermissionDoctor;
use Libinkk\Permission\Support\PermissionValidator;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/permission.php', 'permission');

        $this->app->singleton(PermissionCacheContract::class, PermissionCache::class);

        $this->app->singleton(PermissionRepositoryContract::class, EloquentPermissionRepository::class);
        $this->app->singleton(RoleRepositoryContract::class, EloquentRoleRepository::class);

        $this->app->singleton(ConditionRegistry::class);
        $this->app->singleton(ConditionResolver::class);
        $this->app->singleton(RoleHierarchy::class);
        $this->app->singleton(ScopeHierarchy::class);
        $this->app->singleton(ScopeResolver::class);

        $this->app->singleton(PermissionResolver::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(PermissionManager::class);
        $this->app->singleton(RoleManager::class);
        $this->app->singleton(DecisionCache::class);
        $this->app->singleton(AttributeScanner::class);
        $this->app->singleton(PermissionDiscovery::class);
        $this->app->singleton(PermissionValidator::class);
        $this->app->singleton(PermissionDoctor::class);
        $this->app->singleton(UserAccessExporter::class);
        $this->app->singleton(DelegationManager::class);
        $this->app->singleton(ExpirationChecker::class);
        $this->app->singleton(PermissionVersioner::class);
        $this->app->singleton(PermissionHistory::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(AuditLoggerContract::class, fn ($app) => $app->make(AuditLogger::class));

        $this->app->singleton(FrontendPayload::class);
        $this->app->singleton(PermissionMatrix::class);

        $this->app->singleton(AuthorizationEngineContract::class, AuthorizationEngine::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/permission.php' => $this->app->configPath('permission.php'),
        ], 'libinkk-permission-config');

        $this->publishes([
            __DIR__.'/../../resources/js' => $this->app->resourcePath('js/vendor/libinkk-permission'),
        ], 'libinkk-permission-frontend');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->registerFrontendRoutes();
        $this->registerBuiltinConditions();
        $this->registerCommands();
        $this->registerGate();
        $this->registerMiddleware();
        $this->registerBlade();
        $this->registerAuditListeners();
        $this->registerOctaneFlush();
    }

    protected function registerBuiltinConditions(): void
    {
        Condition::define('owner', function (object $user, mixed $resource = null, array $options = []) {
            return OwnershipChecker::owns(
                $user,
                $resource,
                attribute: $options['value'] ?? $options['attribute'] ?? config('permission.ownership.attribute')
            );
        });
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            ResourceCommand::class,
            DiscoverCommand::class,
            SyncCommand::class,
            ValidateCommand::class,
            DoctorCommand::class,
            CacheCommand::class,
            ClearCacheCommand::class,
            ExportUserAccessCommand::class,
        ]);
    }

    protected function registerGate(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! config('permission.enabled', true)) {
                return null;
            }

            if (! is_object($user)) {
                return false;
            }

            $engine = app(AuthorizationEngineContract::class);

            if (! $engine->manages($ability)) {
                return null;
            }

            return $engine->allows($user, $ability, $arguments);
        });
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make('router');

        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
    }

    protected function registerBlade(): void
    {
        Blade::if('role', function ($role) {
            $user = Auth::user();

            return $user && method_exists($user, 'hasRole') && $user->hasRole($role);
        });

        Blade::if('canall', function ($permissions) {
            $user = Auth::user();

            return $user && method_exists($user, 'canAll') && $user->canAll((array) $permissions);
        });

        Blade::directive('permissionPayload', function () {
            return '<?php echo \'<script>window.__LIBINKK_PERMISSION__ = \'.json_encode(permission_payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).\';</script>\'; ?>';
        });
    }

    protected function registerFrontendRoutes(): void
    {
        if (! config('permission.frontend.enabled', false) || ! config('permission.frontend.routes', true)) {
            return;
        }

        $this->app->make('router')->group([
            'prefix' => trim((string) config('permission.frontend.prefix', 'api'), '/'),
            'middleware' => config('permission.frontend.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        });
    }

    protected function registerAuditListeners(): void
    {
        $events = $this->app->make('events');

        $events->listen(PermissionGranted::class, [AuditLogger::class, 'handlePermissionGranted']);
        $events->listen(PermissionRevoked::class, [AuditLogger::class, 'handlePermissionRevoked']);
        $events->listen(RoleAssigned::class, [AuditLogger::class, 'handleRoleAssigned']);
        $events->listen(RoleRemoved::class, [AuditLogger::class, 'handleRoleRemoved']);
        $events->listen(DelegationCreated::class, [AuditLogger::class, 'handleDelegationCreated']);
        $events->listen(DelegationRevoked::class, [AuditLogger::class, 'handleDelegationRevoked']);
        $events->listen(PolicyChanged::class, [AuditLogger::class, 'handlePolicyChanged']);
        $events->listen(AuthorizationAllowed::class, [AuditLogger::class, 'handleAuthorizationAllowed']);
        $events->listen(AuthorizationDenied::class, [AuditLogger::class, 'handleAuthorizationDenied']);
    }

    protected function registerOctaneFlush(): void
    {
        $events = [
            'Laravel\\Octane\\Events\\RequestReceived',
            'Laravel\\Octane\\Events\\TaskReceived',
            'Laravel\\Octane\\Events\\TickReceived',
        ];

        foreach ($events as $event) {
            if (! class_exists($event)) {
                continue;
            }

            $this->app->make('events')->listen($event, function () {
                if ($this->app->bound(PermissionCacheContract::class)) {
                    $this->app->make(PermissionCacheContract::class)->flushRequestCache();
                }

                if ($this->app->bound(ConditionRegistry::class)) {
                    $this->app->make(ConditionRegistry::class)->flush();
                }

                \Libinkk\Permission\Authorization\AuthorizationContext::flush();
                PermissionFake::reset();
            });
        }
    }
}
