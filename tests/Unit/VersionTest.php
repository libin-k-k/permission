<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Support\PermissionDoctor;
use Libinkk\Permission\Tests\TestCase;
use Libinkk\Permission\Version;

class VersionTest extends TestCase
{
    public function test_package_version_is_1_0_0(): void
    {
        $this->assertSame('1.0.0', Version::VERSION);
    }

    public function test_doctor_reports_version(): void
    {
        $result = app(PermissionDoctor::class)->run();

        $this->assertSame('1.0.0', $result['report']['version']);
        $this->assertStringContainsString('1.0.0', $result['checks'][0]['label']);
    }
}
