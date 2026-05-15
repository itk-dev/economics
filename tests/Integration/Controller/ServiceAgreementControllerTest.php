<?php

namespace App\Tests\Integration\Controller;

class ServiceAgreementControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/serviceagreements',
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_USER'],
        );
    }
}
