<?php

namespace App\Tests\Integration\Controller;

class SynchronizationJobControllerTest extends AbstractControllerTestCase
{
    public function testStatusSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/synchronization/status',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_USER'],
        );
    }
}
