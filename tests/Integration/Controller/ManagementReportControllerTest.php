<?php

namespace App\Tests\Integration\Controller;

class ManagementReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/management-report',
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }
}
