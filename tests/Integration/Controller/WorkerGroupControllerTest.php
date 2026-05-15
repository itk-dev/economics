<?php

namespace App\Tests\Integration\Controller;

class WorkerGroupControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/group/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_USER'],
        );
    }
}
