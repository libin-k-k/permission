<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Libinkk\Permission\Attributes\Permission;

class PostControllerFixture
{
    #[Permission('posts.publish', description: 'Publish posts', group: 'Posts')]
    public function publish(): void
    {
    }

    #[Permission(name: 'posts.feature', group: 'Posts')]
    public function feature(): void
    {
    }
}
