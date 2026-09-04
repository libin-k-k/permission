<?php

namespace Libinkk\Permission\Tests\Unit;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Cache\CacheMetrics;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\TestCase;

class OctaneQueueSafetyTest extends TestCase
{
    public function test_flush_does_not_leak_tenant_impersonation_or_fakes(): void
    {
        $kept = $this->user();
        $kept->givePermissionTo('posts.view');
        $this->assertTrue($kept->can('posts.view'));

        AuthorizationContext::tenant(Organization::query()->create(['name' => 'Acme']));
        AuthorizationContext::impersonating($this->user());
        PermissionFake::activate()->allow('posts.delete');
        app(CacheMetrics::class)->hit('l1');

        app(PermissionCache::class)->flushRequestCache();
        AuthorizationContext::flush();
        PermissionFake::reset();
        app(CacheMetrics::class)->flush();

        $this->assertNull(AuthorizationContext::currentTenant());
        $this->assertFalse(AuthorizationContext::isImpersonating());
        $this->assertFalse(PermissionFake::isActive());
        $this->assertSame(0, app(CacheMetrics::class)->snapshot()['l1_hits']);
        $this->assertTrue($kept->can('posts.view'));
    }

    public function test_queue_worker_events_are_registered(): void
    {
        $events = $this->app->make('events');

        $this->assertTrue($events->hasListeners(JobProcessing::class));
        $this->assertTrue($events->hasListeners(JobProcessed::class));
        $this->assertTrue($events->hasListeners(JobFailed::class));
    }
}
