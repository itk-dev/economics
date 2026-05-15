<?php

namespace App\Tests\Integration\Controller;

class ForecastReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/forecast_report/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_REPORT'],
        );
    }
}
