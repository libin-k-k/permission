<?php

namespace Libinkk\Permission\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Providers\PermissionServiceProvider;
use Libinkk\Permission\Testing\InteractsWithAuthorization;
use Libinkk\Permission\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use InteractsWithAuthorization;

    protected function setUp(): void
    {
        parent::setUp();

        PermissionFake::reset();
    }

    protected function tearDown(): void
    {
        PermissionFake::reset();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/EjwRLtnTUhLSvJ7vKzQ5iKjFk=');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('permission.models.user', User::class);
        $app['config']->set('permission.cache.store', 'array');
        $app['config']->set('permission.cache.driver', 'array');
        $app['config']->set('permission.cache.lock.enabled', false);
        $app['config']->set('permission.default_guard', 'web');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('guard_name')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/permission-check', fn () => 'ok')->middleware('permission:posts.create');
        $router->get('/permission-any', fn () => 'ok')->middleware('permission:posts.create|posts.update');
        $router->get('/role-check', fn () => 'ok')->middleware('role:admin');
        $router->get('/role-any', fn () => 'ok')->middleware('role:admin|editor');
    }

    protected function user(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test User',
            'email' => 'user'.uniqid('', true).'@example.com',
        ], $attributes));
    }
}
