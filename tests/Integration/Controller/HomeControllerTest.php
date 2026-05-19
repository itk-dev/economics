<?php

namespace App\Tests\Integration\Controller;

class HomeControllerTest extends AbstractControllerTestCase
{
    public function testIndexRedirectsAnonymous(): void
    {
        $this->assertAnonymousRedirectsToLogin('/admin/');
    }

    public function testIndexAccessibleToAuthenticated(): void
    {
        $this->assertGrantedFor('/admin/', ['ROLE_USER']);
    }
}
