<?php

namespace App\Tests\Integration\Controller;

class HourReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/hour_report/',
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }
}
