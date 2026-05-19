<?php

namespace App\Tests\Integration\Controller;

class ProductControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/products/',
            allowedRoles: ['ROLE_PRODUCT_MANAGER'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }
}
