<?php

namespace App\Tests\Integration\Controller;

class WorkloadReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/workload_report/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_REPORT'],
        );
    }
}
