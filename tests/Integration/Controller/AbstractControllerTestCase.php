<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractControllerTestCase extends WebTestCase
{
    private const ROLE_USER_FIXTURES = [
        'ROLE_ADMIN' => 'admin@test.local',
        'ROLE_USER' => 'user@test.local',
        'ROLE_INVOICE' => 'invoice@test.local',
        'ROLE_PROJECT_BILLING' => 'project-billing@test.local',
        'ROLE_PLANNING' => 'planning@test.local',
        'ROLE_REPORT' => 'report@test.local',
        'ROLE_PRODUCT_MANAGER' => 'product-manager@test.local',
    ];

    protected function getUserWithRole(string $role): User
    {
        $email = self::ROLE_USER_FIXTURES[$role]
            ?? throw new \InvalidArgumentException(sprintf('No fixture user for role %s.', $role));

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        if (null === $user) {
            throw new \RuntimeException(sprintf('Fixture user %s not found; run `task fixtures`.', $email));
        }

        return $user;
    }

    protected function createClientLoggedInAs(array $roles): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->loginUser($this->getUserWithRole($roles[0]));

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
