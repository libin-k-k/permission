<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Libinkk\Permission\Filament\AuthorizesFilamentWidget;

class ReportsWidgetStub
{
    use AuthorizesFilamentWidget;

    public static ?string $permission = 'dashboard.reports.view';
}
