<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Enum\InvoiceEntryTypeEnum;

class InvoiceEntryWorklogFlowTest extends AbstractTransactionalFlowTestCase
{
    private int $projectId;
    private int $invoiceId;
    private int $worklogEntryId;
    private int $manualEntryId;

    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_INVOICE');

        $worklog = $this->findOne(Worklog::class, ['isBilled' => false, 'invoiceEntry' => null]);
        $project = $this->requireEntity(Project::class, $worklog->getProject());
        $this->projectId = $this->requireId($project->getId());

        $invoice = new Invoice();
        $invoice->setName('Worklog flow invoice');
        $invoice->setProject($project);
        $invoice->setRecorded(false);
        $invoice->setPeriodFrom(new \DateTime('2000-01-01'));
        $invoice->setPeriodTo(new \DateTime('2100-01-01'));
        $this->entityManager->persist($invoice);

        $this->entityManager->persist($worklogEntry = $this->makeEntry($invoice, InvoiceEntryTypeEnum::WORKLOG));
        $this->entityManager->persist($manualEntry = $this->makeEntry($invoice, InvoiceEntryTypeEnum::MANUAL));

        $this->entityManager->flush();

        $this->invoiceId = $this->requireId($invoice->getId());
        $this->worklogEntryId = $this->requireId($worklogEntry->getId());
        $this->manualEntryId = $this->requireId($manualEntry->getId());
        $this->entityManager->clear();
    }

    public function testWorklogsPageIsRendered(): void
    {
        $this->client->request('GET', $this->worklogsUrl());

        $this->assertResponseIsSuccessful();
    }

    public function testWorklogsPageOffersVersionAndEpicFilters(): void
    {
        $crawler = $this->client->request('GET', $this->worklogsUrl());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[name="invoice_entry_worklog_filter[version]"]'));
        $this->assertGreaterThan(0, $crawler->filter('[name="invoice_entry_worklog_filter[epics][]"]')->count());
    }

    public function testWorklogsPagePreFillsThePeriodFromTheInvoice(): void
    {
        $crawler = $this->client->request('GET', $this->worklogsUrl());

        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form();
        $this->assertSame('2000-01-01', $this->fieldValue($form, 'invoice_entry_worklog_filter[periodFrom]'));
        $this->assertSame('2100-01-01', $this->fieldValue($form, 'invoice_entry_worklog_filter[periodTo]'));
    }

    public function testWorklogsPageRejectsNonWorklogEntries(): void
    {
        $this->client->request('GET', sprintf(
            '/admin/invoices/%d/entries/%d/worklogs',
            $this->invoiceId,
            $this->manualEntryId
        ));

        // EconomicsException carries the 400 in its body, not in the HTTP status.
        $this->assertResponseStatusCodeSame(500);
    }

    public function testWorklogsPageRejectsInvoicesWithoutAProject(): void
    {
        $invoiceId = $this->persistProjectlessInvoiceWithWorklogEntry();

        $this->client->request('GET', sprintf(
            '/admin/invoices/%d/entries/%d/worklogs',
            $invoiceId[0],
            $invoiceId[1]
        ));

        $this->assertResponseStatusCodeSame(500);
    }

    public function testShowWorklogsIsRendered(): void
    {
        $this->client->request('GET', sprintf(
            '/admin/invoices/%d/entries/%d/worklogs-show',
            $this->invoiceId,
            $this->worklogEntryId
        ));

        $this->assertResponseIsSuccessful();
    }

    public function testShowWorklogsRejectsNonWorklogEntries(): void
    {
        $this->client->request('GET', sprintf(
            '/admin/invoices/%d/entries/%d/worklogs-show',
            $this->invoiceId,
            $this->manualEntryId
        ));

        $this->assertResponseStatusCodeSame(500);
    }

    public function testSelectingAWorklogAttachesItToTheEntry(): void
    {
        $worklogId = $this->unassignedWorklogId();

        $this->postSelection([['id' => $worklogId, 'checked' => true]]);

        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $worklog = $this->findById(Worklog::class, $worklogId);
        $invoiceEntry = $this->requireEntity(InvoiceEntry::class, $worklog->getInvoiceEntry());
        $this->assertSame($this->worklogEntryId, $invoiceEntry->getId());
    }

    public function testDeselectingAWorklogDetachesItFromTheEntry(): void
    {
        $worklogId = $this->unassignedWorklogId();

        $this->postSelection([['id' => $worklogId, 'checked' => true]]);
        $this->assertResponseIsSuccessful();

        $this->postSelection([['id' => $worklogId, 'checked' => false]]);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $this->assertNull(
            $this->findById(Worklog::class, $worklogId)->getInvoiceEntry()
        );
    }

    public function testSelectingAnAlreadyBilledWorklogIsRejected(): void
    {
        $worklogId = $this->unassignedWorklogId();
        $this->markWorklogBilled($worklogId);

        $this->postSelection([['id' => $worklogId, 'checked' => true]]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertArrayHasKey('message', $this->responseJson());
    }

    public function testSelectingAWorklogOwnedByAnotherEntryIsRejected(): void
    {
        $worklogId = $this->unassignedWorklogId();
        $this->attachWorklogTo($worklogId, $this->manualEntryId);

        $this->postSelection([['id' => $worklogId, 'checked' => true]]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testSelectingAnUnknownWorklogIsNotFound(): void
    {
        $this->postSelection([['id' => 99999999, 'checked' => true]]);

        $this->assertResponseStatusCodeSame(500);
    }

    private function worklogsUrl(): string
    {
        return sprintf('/admin/invoices/%d/entries/%d/worklogs', $this->invoiceId, $this->worklogEntryId);
    }

    /**
     * @param array<int, array{id: int, checked: bool}> $selections
     */
    private function postSelection(array $selections): void
    {
        $this->requestJson(
            'POST',
            sprintf('/admin/invoices/%d/entries/%d/select_worklogs', $this->invoiceId, $this->worklogEntryId),
            $selections
        );
    }

    private function makeEntry(Invoice $invoice, InvoiceEntryTypeEnum $type): InvoiceEntry
    {
        $entry = new InvoiceEntry();
        $entry->setInvoice($invoice);
        $entry->setEntryType($type);
        $entry->setIndex(0);
        $entry->setPrice(100.0);
        $entry->setAmount(1.0);
        $entry->setTotalPrice(100.0);

        return $entry;
    }

    private function unassignedWorklogId(): int
    {
        $worklog = $this->findOne(Worklog::class, ['project' => $this->projectId, 'isBilled' => false, 'invoiceEntry' => null]);

        $id = $this->requireId($worklog->getId());
        $this->entityManager->clear();

        return $id;
    }

    private function markWorklogBilled(int $worklogId): void
    {
        $worklog = $this->findById(Worklog::class, $worklogId);
        $worklog->setIsBilled(true);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function attachWorklogTo(int $worklogId, int $invoiceEntryId): void
    {
        $worklog = $this->findById(Worklog::class, $worklogId);
        $worklog->setInvoiceEntry($this->findById(InvoiceEntry::class, $invoiceEntryId));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function persistProjectlessInvoiceWithWorklogEntry(): array
    {
        $invoice = new Invoice();
        $invoice->setName('Projectless invoice');
        $invoice->setRecorded(false);
        $this->entityManager->persist($invoice);

        $entry = $this->makeEntry($invoice, InvoiceEntryTypeEnum::WORKLOG);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $ids = [$this->requireId($invoice->getId()), $this->requireId($entry->getId())];
        $this->entityManager->clear();

        return $ids;
    }
}
