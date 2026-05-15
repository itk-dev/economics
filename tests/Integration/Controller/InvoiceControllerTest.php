<?php

namespace App\Tests\Integration\Controller;

class InvoiceControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/invoices/',
            allowedRoles: ['ROLE_INVOICE'],
            deniedRoles: ['ROLE_PLANNING'],
        );
    }
}
