<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractControllerTestCase extends WebTestCase
{
    protected function createUser(array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail(sprintf('test-%s@test.com', uniqid('', true)));
        $user->setName('Test User');
        $user->setRoles($roles);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function createClientLoggedInAs(array $roles): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->loginUser($this->createUser($roles));

        return $client;
    }

    protected function assertAnonymousRedirectsToLogin(string $url): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseRedirects();
    }

    protected function assertGrantedFor(string $url, array $roles): void
    {
        $client = $this->createClientLoggedInAs($roles);
        $client->followRedirects(true);
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful(sprintf('Expected 2xx at %s for roles [%s]', $url, implode(',', $roles)));
    }

    protected function assertDeniedFor(string $url, array $roles): void
    {
        $client = $this->createClientLoggedInAs($roles);
        $client->request('GET', $url);

        $this->assertResponseStatusCodeSame(403, sprintf('Expected 403 at %s for roles [%s]', $url, implode(',', $roles)));
    }

    /**
     * Smoke matrix: anonymous redirects, an allowed role gets 200, a denied role gets 403.
     */
    protected function assertSmokeMatrix(string $url, array $allowedRoles, array $deniedRoles): void
    {
        $this->assertAnonymousRedirectsToLogin($url);
        $this->assertGrantedFor($url, $allowedRoles);
        $this->assertDeniedFor($url, $deniedRoles);
    }
}
