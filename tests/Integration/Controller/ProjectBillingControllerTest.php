<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectBillingControllerTest extends WebTestCase
{
    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test-pb-' . uniqid() . '@test.com');
        $user->setName('Test User');
        $user->setRoles($roles);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testIndexRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/project-billing/');

        $this->assertResponseRedirects();
    }

    public function testIndexReturns200ForAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_ADMIN']);

        $client->loginUser($user);
        $client->request('GET', '/admin/project-billing/');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexForbiddenForBasicUser(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_USER']);

        $client->loginUser($user);
        $client->request('GET', '/admin/project-billing/');

        $this->assertResponseStatusCodeSame(403);
    }
}
