<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Enum\InvoiceEntryTypeEnum;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceEntryFlowTest extends AbstractControllerTestCase
{
    public function testEditMutatesEntryAndUpdatesTotalPrice(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        [$invoice, $entry] = $this->setupInvoiceWithManualEntry();

        $crawler = $client->request(
            'GET',
            sprintf('/admin/invoices/%d/entries/%d/edit', $invoice->getId(), $entry->getId()),
        );
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="invoice_entry"]')->form();
        $form['invoice_entry[product]'] = 'Updated product';
        $form['invoice_entry[price]'] = '200';
        $form['invoice_entry[amount]'] = '5';
        $client->submit($form);
        $this->assertResponseIsSuccessful();

        $reloaded = $this->reloadEntry($entry->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Updated product', $reloaded->getProduct());
        $this->assertEqualsWithDelta(200.0, $reloaded->getPrice(), 0.001);
        $this->assertEqualsWithDelta(5.0, $reloaded->getAmount(), 0.001);
        $this->assertEqualsWithDelta(1000.0, $reloaded->getTotalPrice(), 0.001);
    }

    public function testDeleteRemovesEntry(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        [$invoice, $entry] = $this->setupInvoiceWithManualEntry();
        $invoiceId = $invoice->getId();
        $entryId = $entry->getId();

        $crawler = $client->request(
            'GET',
            sprintf('/admin/invoices/%d/entries/%d/edit', $invoiceId, $entryId),
        );
        $this->assertResponseIsSuccessful();

        $deleteForm = $crawler->filter(
            sprintf('form[action$="/admin/invoices/%d/entries/%d"]', $invoiceId, $entryId)
        )->form();
        $client->submit($deleteForm);
        $this->assertResponseRedirects(sprintf('/admin/invoices/%d/edit', $invoiceId));

        $this->assertNull($this->reloadEntry($entryId), 'Expected entry to be deleted.');
    }

    public function testCannotAddEntryToRecordedInvoice(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        [$invoice] = $this->setupInvoiceWithManualEntry(recorded: true);

        $client->request(
            'GET',
            sprintf('/admin/invoices/%d/entries/new/%s', $invoice->getId(), InvoiceEntryTypeEnum::MANUAL->value),
        );
        $this->assertResponseStatusCodeSame(500);
    }

    public function testCannotEditEntryOfRecordedInvoice(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        [$invoice, $entry] = $this->setupInvoiceWithManualEntry();
        $invoiceId = $invoice->getId();
        $entryId = $entry->getId();

        $crawler = $client->request(
            'GET',
            sprintf('/admin/invoices/%d/entries/%d/edit', $invoiceId, $entryId),
        );
        $this->assertResponseIsSuccessful();

        $this->markInvoiceRecorded($invoiceId);

        $form = $crawler->filter('form[name="invoice_entry"]')->form();
        $form['invoice_entry[product]'] = 'Should not save';
        $form['invoice_entry[price]'] = '999';
        $client->submit($form);
        $this->assertResponseStatusCodeSame(500);

        $reloaded = $this->reloadEntry($entryId);
        $this->assertNotNull($reloaded);
        $this->assertNotSame('Should not save', $reloaded->getProduct());
    }

    public function testCannotDeleteEntryOfRecordedInvoice(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        [$invoice, $entry] = $this->setupInvoiceWithManualEntry();
        $invoiceId = $invoice->getId();
        $entryId = $entry->getId();

        $crawler = $client->request(
            'GET',
            sprintf('/admin/invoices/%d/entries/%d/edit', $invoiceId, $entryId),
        );
        $this->assertResponseIsSuccessful();
        $deleteForm = $crawler->filter(
            sprintf('form[action$="/admin/invoices/%d/entries/%d"]', $invoiceId, $entryId)
        )->form();

        $this->markInvoiceRecorded($invoiceId);

        $client->submit($deleteForm);
        $this->assertResponseStatusCodeSame(500);

        $this->assertNotNull(
            $this->reloadEntry($entryId),
            'Entry must not be deleted while the invoice is recorded.'
        );
    }

    public function testDeleteWithInvalidCsrfTokenLeavesEntryInPlace(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        [$invoice, $entry] = $this->setupInvoiceWithManualEntry();
        $invoiceId = $invoice->getId();
        $entryId = $entry->getId();

        $client->request(
            'POST',
            sprintf('/admin/invoices/%d/entries/%d', $invoiceId, $entryId),
            ['_token' => 'invalid-token'],
        );
        $this->assertResponseRedirects(sprintf('/admin/invoices/%d/edit', $invoiceId));

        $this->assertNotNull(
            $this->reloadEntry($entryId),
            'Entry must not be deleted with an invalid CSRF token.'
        );
    }

    /**
     * @return array{0: Invoice, 1: InvoiceEntry}
     */
    private function setupInvoiceWithManualEntry(bool $recorded = false): array
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $projectRepository = $container->get(ProjectRepository::class);
        \assert($projectRepository instanceof ProjectRepository);
        $project = $projectRepository->getIncluded()
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $this->assertNotNull($project, 'Expected an included project from fixtures.');

        $invoice = new Invoice();
        $invoice->setName('EntryFlow-'.uniqid());
        $invoice->setProject($project);
        $invoice->setRecorded($recorded);
        if ($recorded) {
            $invoice->setRecordedDate(new \DateTime());
        }
        $em->persist($invoice);

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry->setProduct('Initial product');
        $entry->setPrice(100.0);
        $entry->setAmount(2.0);
        $entry->setTotalPrice(200.0);
        $entry->setInvoice($invoice);
        $invoice->addInvoiceEntry($entry);
        $em->persist($entry);

        $em->flush();
        $invoiceId = $invoice->getId();
        $entryId = $entry->getId();
        $em->clear();

        $invoiceRepository = $container->get(InvoiceRepository::class);
        \assert($invoiceRepository instanceof InvoiceRepository);
        $invoice = $invoiceRepository->find($invoiceId);
        $this->assertNotNull($invoice);
        $invoiceEntryRepository = $container->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepository instanceof InvoiceEntryRepository);
        $entry = $invoiceEntryRepository->find($entryId);
        $this->assertNotNull($entry);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceOf(InvoiceEntry::class, $entry);

        return [$invoice, $entry];
    }

    private function markInvoiceRecorded(int $invoiceId): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $invoiceRepository = static::getContainer()->get(InvoiceRepository::class);
        \assert($invoiceRepository instanceof InvoiceRepository);
        $invoice = $invoiceRepository->find($invoiceId);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $invoice->setRecorded(true);
        $invoice->setRecordedDate(new \DateTime());
        $em->flush();
        $em->clear();
    }

    private function reloadEntry(int $id): ?InvoiceEntry
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->clear();

        $invoiceEntryRepository = static::getContainer()->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepository instanceof InvoiceEntryRepository);

        return $invoiceEntryRepository->find($id);
    }
}
