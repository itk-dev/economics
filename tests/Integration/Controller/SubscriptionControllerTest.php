<?php

namespace App\Tests\Integration\Controller;

class SubscriptionControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/subscription/',
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }
}
