<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HourReportControllerTest extends WebTestCase
{
    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test-hr-' . uniqid() . '@test.com');
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

        $client->request('GET', '/admin/reports/hour_report/');

        $this->assertResponseRedirects();
    }

    public function testIndexReturns200ForAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_ADMIN']);

        $client->loginUser($user);
        $client->request('GET', '/admin/reports/hour_report/');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexForbiddenForBasicUser(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_USER']);

        $client->loginUser($user);
        $client->request('GET', '/admin/reports/hour_report/');

        $this->assertResponseStatusCodeSame(403);
    }
}
