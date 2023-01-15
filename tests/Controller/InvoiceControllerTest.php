<?php

namespace App\Test\Controller;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InvoiceControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private InvoiceRepository $repository;
    private string $path = '/invoice/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->repository = static::getContainer()->get('doctrine')->getRepository(Invoice::class);

        foreach ($this->repository->findAll() as $object) {
            $this->repository->remove($object, true);
        }
    }

    public function testIndex(): void
    {
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Invoice index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $originalNumObjectsInRepository = count($this->repository->findAll());

        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'invoice[name]' => 'Testing',
            'invoice[description]' => 'Testing',
            'invoice[projectId]' => 'Testing',
            'invoice[recorded]' => 'Testing',
            'invoice[customerAccountId]' => 'Testing',
            'invoice[recordedDate]' => 'Testing',
            'invoice[exportedDate]' => 'Testing',
            'invoice[lockedContactName]' => 'Testing',
            'invoice[lockedType]' => 'Testing',
            'invoice[lockedAccountKey]' => 'Testing',
            'invoice[lockedSalesChannel]' => 'Testing',
            'invoice[paidByAccount]' => 'Testing',
            'invoice[defaultPayToAccount]' => 'Testing',
            'invoice[defaultMaterialNumber]' => 'Testing',
            'invoice[periodFrom]' => 'Testing',
            'invoice[periodTo]' => 'Testing',
            'invoice[createdBy]' => 'Testing',
            'invoice[updatedBy]' => 'Testing',
            'invoice[createdAt]' => 'Testing',
            'invoice[updatedAt]' => 'Testing',
            'invoice[project]' => 'Testing',
        ]);

        self::assertResponseRedirects('/invoice/');

        self::assertSame($originalNumObjectsInRepository + 1, count($this->repository->findAll()));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Invoice();
        $fixture->setName('My Title');
        $fixture->setDescription('My Title');
        $fixture->setProjectId('My Title');
        $fixture->setRecorded('My Title');
        $fixture->setCustomerAccountId('My Title');
        $fixture->setRecordedDate('My Title');
        $fixture->setExportedDate('My Title');
        $fixture->setLockedContactName('My Title');
        $fixture->setLockedType('My Title');
        $fixture->setLockedAccountKey('My Title');
        $fixture->setLockedSalesChannel('My Title');
        $fixture->setPaidByAccount('My Title');
        $fixture->setDefaultPayToAccount('My Title');
        $fixture->setDefaultMaterialNumber('My Title');
        $fixture->setPeriodFrom('My Title');
        $fixture->setPeriodTo('My Title');
        $fixture->setCreatedBy('My Title');
        $fixture->setUpdatedBy('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setProject('My Title');

        $this->repository->add($fixture, true);

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Invoice');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Invoice();
        $fixture->setName('My Title');
        $fixture->setDescription('My Title');
        $fixture->setProjectId('My Title');
        $fixture->setRecorded('My Title');
        $fixture->setCustomerAccountId('My Title');
        $fixture->setRecordedDate('My Title');
        $fixture->setExportedDate('My Title');
        $fixture->setLockedContactName('My Title');
        $fixture->setLockedType('My Title');
        $fixture->setLockedAccountKey('My Title');
        $fixture->setLockedSalesChannel('My Title');
        $fixture->setPaidByAccount('My Title');
        $fixture->setDefaultPayToAccount('My Title');
        $fixture->setDefaultMaterialNumber('My Title');
        $fixture->setPeriodFrom('My Title');
        $fixture->setPeriodTo('My Title');
        $fixture->setCreatedBy('My Title');
        $fixture->setUpdatedBy('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setProject('My Title');

        $this->repository->add($fixture, true);

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'invoice[name]' => 'Something New',
            'invoice[description]' => 'Something New',
            'invoice[projectId]' => 'Something New',
            'invoice[recorded]' => 'Something New',
            'invoice[customerAccountId]' => 'Something New',
            'invoice[recordedDate]' => 'Something New',
            'invoice[exportedDate]' => 'Something New',
            'invoice[lockedContactName]' => 'Something New',
            'invoice[lockedType]' => 'Something New',
            'invoice[lockedAccountKey]' => 'Something New',
            'invoice[lockedSalesChannel]' => 'Something New',
            'invoice[paidByAccount]' => 'Something New',
            'invoice[defaultPayToAccount]' => 'Something New',
            'invoice[defaultMaterialNumber]' => 'Something New',
            'invoice[periodFrom]' => 'Something New',
            'invoice[periodTo]' => 'Something New',
            'invoice[createdBy]' => 'Something New',
            'invoice[updatedBy]' => 'Something New',
            'invoice[createdAt]' => 'Something New',
            'invoice[updatedAt]' => 'Something New',
            'invoice[project]' => 'Something New',
        ]);

        self::assertResponseRedirects('/invoice/');

        $fixture = $this->repository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getDescription());
        self::assertSame('Something New', $fixture[0]->getProjectId());
        self::assertSame('Something New', $fixture[0]->getRecorded());
        self::assertSame('Something New', $fixture[0]->getCustomerAccountId());
        self::assertSame('Something New', $fixture[0]->getRecordedDate());
        self::assertSame('Something New', $fixture[0]->getExportedDate());
        self::assertSame('Something New', $fixture[0]->getLockedContactName());
        self::assertSame('Something New', $fixture[0]->getLockedType());
        self::assertSame('Something New', $fixture[0]->getLockedAccountKey());
        self::assertSame('Something New', $fixture[0]->getLockedSalesChannel());
        self::assertSame('Something New', $fixture[0]->getPaidByAccount());
        self::assertSame('Something New', $fixture[0]->getDefaultPayToAccount());
        self::assertSame('Something New', $fixture[0]->getDefaultMaterialNumber());
        self::assertSame('Something New', $fixture[0]->getPeriodFrom());
        self::assertSame('Something New', $fixture[0]->getPeriodTo());
        self::assertSame('Something New', $fixture[0]->getCreatedBy());
        self::assertSame('Something New', $fixture[0]->getUpdatedBy());
        self::assertSame('Something New', $fixture[0]->getCreatedAt());
        self::assertSame('Something New', $fixture[0]->getUpdatedAt());
        self::assertSame('Something New', $fixture[0]->getProject());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();

        $originalNumObjectsInRepository = count($this->repository->findAll());

        $fixture = new Invoice();
        $fixture->setName('My Title');
        $fixture->setDescription('My Title');
        $fixture->setProjectId('My Title');
        $fixture->setRecorded('My Title');
        $fixture->setCustomerAccountId('My Title');
        $fixture->setRecordedDate('My Title');
        $fixture->setExportedDate('My Title');
        $fixture->setLockedContactName('My Title');
        $fixture->setLockedType('My Title');
        $fixture->setLockedAccountKey('My Title');
        $fixture->setLockedSalesChannel('My Title');
        $fixture->setPaidByAccount('My Title');
        $fixture->setDefaultPayToAccount('My Title');
        $fixture->setDefaultMaterialNumber('My Title');
        $fixture->setPeriodFrom('My Title');
        $fixture->setPeriodTo('My Title');
        $fixture->setCreatedBy('My Title');
        $fixture->setUpdatedBy('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setProject('My Title');

        $this->repository->add($fixture, true);

        self::assertSame($originalNumObjectsInRepository + 1, count($this->repository->findAll()));

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertSame($originalNumObjectsInRepository, count($this->repository->findAll()));
        self::assertResponseRedirects('/invoice/');
    }
}
