<?php

namespace App\Tests\Integration\Controller;

class ClientControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/client/',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_USER'],
        );
    }
}
