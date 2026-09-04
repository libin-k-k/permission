<?php

namespace Libinkk\Permission\Tests\Feature;

use Libinkk\Permission\Tests\TestCase;

class AuthorizationDebugApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('permission.debug.enabled', true);
    }

    public function test_explain_endpoint_is_self_only_and_not_a_grant(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->getJson('/api/authorization/explain?permission=posts.delete')
            ->assertOk()
            ->assertJsonPath('final', 'DENIED')
            ->assertJsonPath('action', 'posts.delete');

        $this->assertFalse($user->fresh()->can('posts.delete'));
    }

    public function test_explain_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/authorization/explain?permission=posts.view')
            ->assertUnauthorized();
    }

    public function test_explain_endpoint_requires_permission_query(): void
    {
        $this->actingAs($this->user());

        $this->getJson('/api/authorization/explain')
            ->assertStatus(422);
    }
}
