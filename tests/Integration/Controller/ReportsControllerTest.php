<?php

namespace App\Tests\Integration\Controller;

class ReportsControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports',
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }
}
