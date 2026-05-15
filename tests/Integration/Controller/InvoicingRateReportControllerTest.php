<?php

namespace App\Tests\Integration\Controller;

class InvoicingRateReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/invoicing_rate_report/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_REPORT'],
        );
    }
}
