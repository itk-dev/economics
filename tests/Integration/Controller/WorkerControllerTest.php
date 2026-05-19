<?php

namespace App\Tests\Integration\Controller;

class WorkerControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/worker/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_USER'],
        );
    }
}
