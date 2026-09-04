<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Tests\TestCase;

class FrontendHelpersTest extends TestCase
{
    public function test_vue_and_react_helpers_are_shipped(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js';

        $vue = file_get_contents($root.'/vue/index.js');
        $react = file_get_contents($root.'/react/index.js');
        $store = file_get_contents($root.'/shared/store.js');

        $this->assertStringContainsString('export function createPermissionPlugin', $vue);
        $this->assertStringContainsString('export function usePermission', $vue);
        $this->assertStringContainsString('$can', $vue);
        $this->assertStringContainsString('$hasRole', $vue);
        $this->assertStringContainsString('export function Can', $react);
        $this->assertStringContainsString('export function CanAny', $react);
        $this->assertStringContainsString('export function CanAll', $react);
        $this->assertStringContainsString('export function usePermission', $react);
        $this->assertStringContainsString('isDenied', $store);
        $this->assertStringContainsString('matchesWildcard', $store);
    }
}
