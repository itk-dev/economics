<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use App\Enum\RolesEnum;

class UserFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootTransactionalClient('ROLE_ADMIN');
    }

    public function testIndexListsUsers(): void
    {
        $crawler = $this->client->request('GET', '/admin/users/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('admin@test.local', $crawler->html());
    }

    public function testUpdateRoleGrantsARole(): void
    {
        $id = $this->persistUser('grant@test.local', []);

        $this->postRole($id, RolesEnum::ROLE_REPORT->value, true);

        $this->assertResponseIsSuccessful();
        $this->assertContains(
            RolesEnum::ROLE_REPORT->value,
            $this->responseJson()['roles']
        );

        $this->entityManager->clear();
        $this->assertContains(
            RolesEnum::ROLE_REPORT->value,
            $this->findById(User::class, $id)->getRoles()
        );
    }

    public function testGrantingATwiceHeldRoleDoesNotDuplicateIt(): void
    {
        $id = $this->persistUser('duplicate@test.local', [RolesEnum::ROLE_REPORT->value]);

        $this->postRole($id, RolesEnum::ROLE_REPORT->value, true);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [RolesEnum::ROLE_REPORT->value],
            array_values($this->responseJson()['roles'])
        );
    }

    public function testUpdateRoleRevokesARole(): void
    {
        $id = $this->persistUser('revoke@test.local', [RolesEnum::ROLE_REPORT->value, RolesEnum::ROLE_INVOICE->value]);

        $this->postRole($id, RolesEnum::ROLE_REPORT->value, false);

        $this->assertResponseIsSuccessful();
        $roles = $this->responseJson()['roles'];
        $this->assertNotContains(RolesEnum::ROLE_REPORT->value, $roles);
        $this->assertContains(RolesEnum::ROLE_INVOICE->value, $roles);
    }

    public function testRevokingAnAbsentRoleIsANoop(): void
    {
        $id = $this->persistUser('absent@test.local', [RolesEnum::ROLE_INVOICE->value]);

        $this->postRole($id, RolesEnum::ROLE_REPORT->value, false);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [RolesEnum::ROLE_INVOICE->value],
            array_values($this->responseJson()['roles'])
        );
    }

    public function testAdminsCannotEditTheirOwnRoles(): void
    {
        $ownId = $this->requireId($this->findOne(User::class, ['email' => 'admin@test.local'])->getId());

        $this->postRole($ownId, RolesEnum::ROLE_REPORT->value, true);

        $this->assertResponseStatusCodeSame(400);
    }

    private function postRole(int $userId, string $key, bool $value): void
    {
        $this->requestJson('POST', sprintf('/admin/users/%d/update_role', $userId), ['key' => $key, 'value' => $value]);
    }

    /**
     * @param string[] $roles
     */
    private function persistUser(string $email, array $roles): int
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName($email);
        $user->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $id = $this->requireId($user->getId());
        $this->entityManager->clear();

        return $id;
    }
}
