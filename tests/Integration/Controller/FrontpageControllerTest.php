<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FrontpageControllerTest extends WebTestCase
{
    public function testIndexReturns200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }
}
