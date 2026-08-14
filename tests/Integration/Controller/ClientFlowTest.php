<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Client;
use App\Enum\ClientTypeEnum;

class ClientFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_ADMIN');
    }

    public function testIndexListsClients(): void
    {
        $crawler = $this->client->request('GET', '/admin/client/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('client 0-0', $crawler->html());
    }

    public function testNewFormIsRendered(): void
    {
        $this->client->request('GET', '/admin/client/new');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesAClient(): void
    {
        $this->submitFormAt('/admin/client/new', 'client', [
            'client[name]' => 'Client Created By Test',
            'client[contact]' => 'Jane Doe',
            'client[standardPrice]' => '950',
            'client[type]' => ClientTypeEnum::EXTERNAL_WITH_MOMS->value,
            'client[customerKey]' => 'CUST-TEST',
            'client[psp]' => 'PSP-TEST',
            'client[ean]' => '5790000000000',
        ]);

        $this->assertResponseRedirects('/admin/client/');

        $this->entityManager->clear();
        $client = $this->findOne(Client::class, ['name' => 'Client Created By Test']);
        $this->assertSame(ClientTypeEnum::EXTERNAL_WITH_MOMS, $client->getType());
        $this->assertSame(950.0, $client->getStandardPrice());
    }

    public function testEditFormIsRendered(): void
    {
        $id = $this->persistClient('Client To Render');

        $this->client->request('GET', sprintf('/admin/client/%d/edit', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testEditUpdatesTheClient(): void
    {
        $id = $this->persistClient('Client To Edit');

        $this->submitFormAt(sprintf('/admin/client/%d/edit', $id), 'client', [
            'client[name]' => 'Client Edited',
            'client[contact]' => 'John Doe',
            'client[standardPrice]' => '1200',
            'client[type]' => ClientTypeEnum::INTERNAL->value,
        ]);

        $this->assertResponseRedirects('/admin/client/');

        $this->entityManager->clear();
        $client = $this->findById(Client::class, $id);
        $this->assertSame('Client Edited', $client->getName());
        $this->assertSame('John Doe', $client->getContact());
        $this->assertSame(1200.0, $client->getStandardPrice());
        $this->assertSame(ClientTypeEnum::INTERNAL, $client->getType());
    }

    public function testEditKeepsTheLegacyExternalTypeSelectable(): void
    {
        $id = $this->persistClient('Legacy Client', ClientTypeEnum::EXTERNAL);

        $crawler = $this->client->request('GET', sprintf('/admin/client/%d/edit', $id));
        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('form[name="client"] select[name="client[type]"] option')
            ->each(fn ($node) => $node->attr('value'));

        $this->assertContains(ClientTypeEnum::EXTERNAL->value, $options);
    }

    public function testNewFormOmitsTheLegacyExternalType(): void
    {
        $crawler = $this->client->request('GET', '/admin/client/new');
        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('form[name="client"] select[name="client[type]"] option')
            ->each(fn ($node) => $node->attr('value'));

        $this->assertNotContains(ClientTypeEnum::EXTERNAL->value, $options);
    }

    public function testDeleteRemovesTheClient(): void
    {
        $id = $this->persistClient('Client To Delete');

        $this->submitDeleteFormAt(sprintf('/admin/client/%d/edit', $id), '/admin/client/'.$id);

        $this->assertResponseRedirects('/admin/client/');

        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(Client::class, $id));
    }

    public function testDeleteWithAnInvalidTokenKeepsTheClient(): void
    {
        $id = $this->persistClient('Client To Keep');

        $this->client->request('POST', '/admin/client/'.$id, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/client/');

        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Client::class, $id));
    }

    private function persistClient(string $name, ?ClientTypeEnum $type = null): int
    {
        $client = new Client();
        $client->setName($name);
        $client->setContact('Contact Person');
        $client->setStandardPrice(500.0);
        $client->setType($type ?? ClientTypeEnum::INTERNAL);
        $this->entityManager->persist($client);
        $this->entityManager->flush();
        $id = $this->requireId($client->getId());
        $this->entityManager->clear();

        return $id;
    }
}
