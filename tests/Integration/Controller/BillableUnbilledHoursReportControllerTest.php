<?php

namespace App\Tests\Integration\Controller;

class BillableUnbilledHoursReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/billable_unbilled_hours_report/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_REPORT'],
        );
    }
}
