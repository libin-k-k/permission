<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Support\PermissionDoctor;
use Libinkk\Permission\Tests\TestCase;
use Libinkk\Permission\Version;

class VersionTest extends TestCase
{
    public function test_package_version_is_1_1_1(): void
    {
        $this->assertSame('1.1.1', Version::VERSION);
    }

    public function test_doctor_reports_version(): void
    {
        $result = app(PermissionDoctor::class)->run();

        $this->assertSame('1.1.1', $result['report']['version']);
        $this->assertStringContainsString('1.1.1', $result['checks'][0]['label']);
    }
}
