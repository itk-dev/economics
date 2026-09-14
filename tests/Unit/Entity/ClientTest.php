<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\DataProvider;
use App\Entity\Invoice;
use App\Entity\Project;
use App\Enum\ClientTypeEnum;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client();
    }

    public function testCollectionsStartEmpty(): void
    {
        $this->assertCount(0, $this->client->getInvoices());
        $this->assertCount(0, $this->client->getProjects());
    }

    public function testScalarAccessors(): void
    {
        $this->client->setName('Acme Corp');
        $this->client->setContact('Jane Doe');
        $this->client->setStandardPrice(950.0);
        $this->client->setType(ClientTypeEnum::EXTERNAL_WITH_MOMS);
        $this->client->setPsp('PSP-1');
        $this->client->setEan('5790000000000');
        $this->client->setProjectTrackerId('client-1');
        $this->client->setCustomerKey('CUST-1');
        $this->client->setVersionName('PB-1');

        $this->assertSame('Acme Corp', $this->client->getName());
        $this->assertSame('Jane Doe', $this->client->getContact());
        $this->assertSame(950.0, $this->client->getStandardPrice());
        $this->assertSame(ClientTypeEnum::EXTERNAL_WITH_MOMS, $this->client->getType());
        $this->assertSame('PSP-1', $this->client->getPsp());
        $this->assertSame('5790000000000', $this->client->getEan());
        $this->assertSame('client-1', $this->client->getProjectTrackerId());
        $this->assertSame('CUST-1', $this->client->getCustomerKey());
        $this->assertSame('PB-1', $this->client->getVersionName());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->client->setContact(null);
        $this->client->setStandardPrice(null);
        $this->client->setType(null);
        $this->client->setPsp(null);
        $this->client->setEan(null);
        $this->client->setProjectTrackerId(null);
        $this->client->setCustomerKey(null);
        $this->client->setVersionName(null);

        $this->assertNull($this->client->getContact());
        $this->assertNull($this->client->getStandardPrice());
        $this->assertNull($this->client->getType());
        $this->assertNull($this->client->getPsp());
        $this->assertNull($this->client->getEan());
        $this->assertNull($this->client->getProjectTrackerId());
        $this->assertNull($this->client->getCustomerKey());
        $this->assertNull($this->client->getVersionName());
    }

    public function testToStringUsesName(): void
    {
        $this->client->setName('Acme Corp');

        $this->assertSame('Acme Corp', (string) $this->client);
    }

    public function testToStringFallsBackToIdWhenNameIsMissing(): void
    {
        $this->assertSame('', (string) $this->client);
    }

    public function testDataProviderAccessor(): void
    {
        $dataProvider = new DataProvider();
        $this->client->setDataProvider($dataProvider);

        $this->assertSame($dataProvider, $this->client->getDataProvider());
    }

    public function testAddInvoiceSetsOwningSide(): void
    {
        $invoice = new Invoice();
        $this->client->addInvoice($invoice);
        $this->client->addInvoice($invoice);

        $this->assertCount(1, $this->client->getInvoices());
        $this->assertSame($this->client, $invoice->getClient());
    }

    public function testRemoveInvoiceClearsOwningSide(): void
    {
        $invoice = new Invoice();
        $this->client->addInvoice($invoice);
        $this->client->removeInvoice($invoice);

        $this->assertCount(0, $this->client->getInvoices());
        $this->assertNull($invoice->getClient());
    }

    public function testRemoveInvoiceLeavesForeignOwnerAlone(): void
    {
        $other = new Client();
        $invoice = new Invoice();
        $other->addInvoice($invoice);
        $this->client->getInvoices()->add($invoice);

        $this->client->removeInvoice($invoice);

        $this->assertSame($other, $invoice->getClient());
    }

    public function testAddProjectKeepsBothSidesInSync(): void
    {
        $project = new Project();
        $this->client->addProject($project);
        $this->client->addProject($project);

        $this->assertCount(1, $this->client->getProjects());
        $this->assertCount(1, $project->getClients());
        $this->assertTrue($project->getClients()->contains($this->client));
    }

    public function testRemoveProjectKeepsBothSidesInSync(): void
    {
        $project = new Project();
        $this->client->addProject($project);
        $this->client->removeProject($project);

        $this->assertCount(0, $this->client->getProjects());
        $this->assertCount(0, $project->getClients());
    }

    public function testSoftDeleteMarker(): void
    {
        $deletedAt = new \DateTime('2026-05-01');

        $this->assertNull($this->client->getDeletedAt());

        $this->client->setDeletedAt($deletedAt);

        $this->assertSame($deletedAt, $this->client->getDeletedAt());
        $this->assertTrue($this->client->isDeleted());
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->client, $this->client->setName('Acme'));
        $this->assertSame($this->client, $this->client->setContact(null));
        $this->assertSame($this->client, $this->client->setStandardPrice(null));
        $this->assertSame($this->client, $this->client->setPsp(null));
        $this->assertSame($this->client, $this->client->setCustomerKey(null));
        $this->assertSame($this->client, $this->client->setVersionName(null));
        $this->assertSame($this->client, $this->client->addInvoice(new Invoice()));
        $this->assertSame($this->client, $this->client->addProject(new Project()));
    }
}
