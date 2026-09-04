<?php

namespace Libinkk\Permission\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class BladeDirectivesTest extends TestCase
{
    public function test_can_directive_uses_gate(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');
        $this->actingAs($user);

        $this->assertSame('yes', trim(Blade::render("@can('posts.create') yes @else no @endcan")));
    }

    public function test_role_directive(): void
    {
        $user = $this->user();
        Role::findOrCreate('admin');
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertSame('yes', trim(Blade::render("@role('admin') yes @else no @endrole")));
        $this->assertSame('no', trim(Blade::render("@role('editor') yes @else no @endrole")));
    }

    public function test_canany_directive(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.update');
        $this->actingAs($user);

        $this->assertSame(
            'yes',
            trim(Blade::render("@canany(['posts.create', 'posts.update']) yes @else no @endcanany"))
        );
    }

    public function test_canall_directive(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');
        $this->actingAs($user);

        $this->assertSame(
            '',
            trim(Blade::render("@canall(['posts.create', 'posts.update']) yes @endcanall"))
        );

        $user->givePermissionTo('posts.update');

        $this->assertSame(
            'yes',
            trim(Blade::render("@canall(['posts.create', 'posts.update']) yes @endcanall"))
        );
    }
}
