<?php

namespace App\Tests\Integration\Controller;

class PlanningControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/planning/',
            allowedRoles: ['ROLE_PLANNING'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }
}
