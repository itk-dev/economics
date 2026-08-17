<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Account;

class AccountFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_ADMIN');
    }

    public function testIndexListsAccounts(): void
    {
        $crawler = $this->client->request('GET', '/admin/account/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test Account 1', $crawler->html());
    }

    public function testNewFormIsRendered(): void
    {
        $this->client->request('GET', '/admin/account/new');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesAnAccount(): void
    {
        $this->submitFormAt('/admin/account/new', 'account', [
            'account[name]' => 'Account Created By Test',
            'account[value]' => '999999',
        ]);

        $this->assertResponseRedirects('/admin/account/');

        $this->entityManager->clear();
        $account = $this->findOne(Account::class, ['name' => 'Account Created By Test']);
        $this->assertSame('999999', $account->getValue());
    }

    public function testEditFormIsRendered(): void
    {
        $id = $this->persistAccount('Account To Render', '111111');

        $this->client->request('GET', sprintf('/admin/account/%d/edit', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testEditUpdatesAnAccount(): void
    {
        $id = $this->persistAccount('Account To Edit', '222222');

        $this->submitFormAt(sprintf('/admin/account/%d/edit', $id), 'account', [
            'account[name]' => 'Account Edited',
            'account[value]' => '333333',
        ]);

        $this->assertResponseRedirects('/admin/account/');

        $this->entityManager->clear();
        $account = $this->findById(Account::class, $id);
        $this->assertSame('Account Edited', $account->getName());
        $this->assertSame('333333', $account->getValue());
    }

    public function testDeleteRemovesTheAccount(): void
    {
        $id = $this->persistAccount('Account To Delete', '444444');

        $this->submitDeleteFormAt(sprintf('/admin/account/%d/edit', $id), '/admin/account/'.$id);

        $this->assertResponseRedirects('/admin/account/');

        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(Account::class, $id));
    }

    public function testDeleteWithAnInvalidTokenKeepsTheAccount(): void
    {
        $id = $this->persistAccount('Account To Keep', '555555');

        $this->client->request('POST', '/admin/account/'.$id, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/account/');

        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Account::class, $id));
    }

    private function persistAccount(string $name, string $value): int
    {
        $account = new Account();
        $account->setName($name);
        $account->setValue($value);
        $this->entityManager->persist($account);
        $this->entityManager->flush();
        $id = $this->requireId($account->getId());
        $this->entityManager->clear();

        return $id;
    }
}
