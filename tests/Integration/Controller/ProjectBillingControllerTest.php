<?php

namespace App\Tests\Integration\Controller;

class ProjectBillingControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/project-billing/',
            allowedRoles: ['ROLE_PROJECT_BILLING'],
            deniedRoles: ['ROLE_PLANNING'],
        );
    }
}
