<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Libinkk\Permission\Filament\AuthorizesFilamentPage;

class ReportsPageStub
{
    use AuthorizesFilamentPage;

    public static ?string $permission = 'reports.view';
}
