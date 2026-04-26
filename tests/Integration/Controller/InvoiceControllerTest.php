<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InvoiceControllerTest extends WebTestCase
{
    private function createAdminUser(): User
    {
        $user = new User();
        $user->setEmail('test-admin@test.com');
        $user->setName('Test Admin');
        $user->setRoles(['ROLE_ADMIN']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testIndexRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/invoices/');

        $this->assertResponseRedirects();
    }

    public function testIndexReturns200ForAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser();

        $client->loginUser($user);
        $client->request('GET', '/admin/invoices/');

        $this->assertResponseIsSuccessful();
    }
}
