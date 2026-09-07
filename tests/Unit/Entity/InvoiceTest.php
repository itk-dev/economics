<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\ProjectBilling;
use App\Enum\MaterialNumberEnum;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    private Invoice $invoice;

    protected function setUp(): void
    {
        $this->invoice = new Invoice();
    }

    public function testDefaults(): void
    {
        $this->assertCount(0, $this->invoice->getInvoiceEntries());
        $this->assertFalse($this->invoice->isNoCost());
        $this->assertNull($this->invoice->isRecorded());
        $this->assertNull($this->invoice->getTotalPrice());
    }

    public function testScalarAccessors(): void
    {
        $recordedDate = new \DateTime('2026-01-15');
        $exportedDate = new \DateTime('2026-01-16');
        $periodFrom = new \DateTime('2026-01-01');
        $periodTo = new \DateTime('2026-01-31');

        $this->invoice->setName('Invoice Alpha');
        $this->invoice->setDescription('January work');
        $this->invoice->setRecorded(true);
        $this->invoice->setCustomerAccountId(4711);
        $this->invoice->setRecordedDate($recordedDate);
        $this->invoice->setExportedDate($exportedDate);
        $this->invoice->setLockedCustomerKey('CUST-1');
        $this->invoice->setLockedContactName('Jane Doe');
        $this->invoice->setLockedType('internal');
        $this->invoice->setLockedEan('5790000000000');
        $this->invoice->setLockedSalesChannel('channel');
        $this->invoice->setPaidByAccount('1234');
        $this->invoice->setDefaultReceiverAccount('5678');
        $this->invoice->setDefaultMaterialNumber(MaterialNumberEnum::INTERNAL);
        $this->invoice->setPeriodFrom($periodFrom);
        $this->invoice->setPeriodTo($periodTo);
        $this->invoice->setTotalPrice(1500.25);
        $this->invoice->setNoCost(true);

        $this->assertSame('Invoice Alpha', $this->invoice->getName());
        $this->assertSame('January work', $this->invoice->getDescription());
        $this->assertTrue($this->invoice->isRecorded());
        $this->assertSame(4711, $this->invoice->getCustomerAccountId());
        $this->assertSame($recordedDate, $this->invoice->getRecordedDate());
        $this->assertSame($exportedDate, $this->invoice->getExportedDate());
        $this->assertSame('CUST-1', $this->invoice->getLockedCustomerKey());
        $this->assertSame('Jane Doe', $this->invoice->getLockedContactName());
        $this->assertSame('internal', $this->invoice->getLockedType());
        $this->assertSame('5790000000000', $this->invoice->getLockedEan());
        $this->assertSame('channel', $this->invoice->getLockedSalesChannel());
        $this->assertSame('1234', $this->invoice->getPaidByAccount());
        $this->assertSame('5678', $this->invoice->getDefaultReceiverAccount());
        $this->assertSame(MaterialNumberEnum::INTERNAL, $this->invoice->getDefaultMaterialNumber());
        $this->assertSame($periodFrom, $this->invoice->getPeriodFrom());
        $this->assertSame($periodTo, $this->invoice->getPeriodTo());
        $this->assertSame(1500.25, $this->invoice->getTotalPrice());
        $this->assertTrue($this->invoice->isNoCost());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->invoice->setDescription(null);
        $this->invoice->setCustomerAccountId(null);
        $this->invoice->setRecordedDate(null);
        $this->invoice->setExportedDate(null);
        $this->invoice->setLockedCustomerKey(null);
        $this->invoice->setLockedContactName(null);
        $this->invoice->setLockedType(null);
        $this->invoice->setLockedEan(null);
        $this->invoice->setLockedSalesChannel(null);
        $this->invoice->setPaidByAccount(null);
        $this->invoice->setDefaultReceiverAccount(null);
        $this->invoice->setDefaultMaterialNumber(null);
        $this->invoice->setPeriodFrom(null);
        $this->invoice->setPeriodTo(null);
        $this->invoice->setTotalPrice(null);

        $this->assertNull($this->invoice->getDescription());
        $this->assertNull($this->invoice->getCustomerAccountId());
        $this->assertNull($this->invoice->getRecordedDate());
        $this->assertNull($this->invoice->getExportedDate());
        $this->assertNull($this->invoice->getLockedCustomerKey());
        $this->assertNull($this->invoice->getLockedContactName());
        $this->assertNull($this->invoice->getLockedType());
        $this->assertNull($this->invoice->getLockedEan());
        $this->assertNull($this->invoice->getLockedSalesChannel());
        $this->assertNull($this->invoice->getPaidByAccount());
        $this->assertNull($this->invoice->getDefaultReceiverAccount());
        $this->assertNull($this->invoice->getDefaultMaterialNumber());
        $this->assertNull($this->invoice->getPeriodFrom());
        $this->assertNull($this->invoice->getPeriodTo());
        $this->assertNull($this->invoice->getTotalPrice());
    }

    public function testRelationAccessors(): void
    {
        $project = new Project();
        $client = new Client();
        $projectBilling = new ProjectBilling();

        $this->invoice->setProject($project);
        $this->invoice->setClient($client);
        $this->invoice->setProjectBilling($projectBilling);

        $this->assertSame($project, $this->invoice->getProject());
        $this->assertSame($client, $this->invoice->getClient());
        $this->assertSame($projectBilling, $this->invoice->getProjectBilling());
    }

    public function testAddInvoiceEntrySetsOwningSide(): void
    {
        $entry = new InvoiceEntry();
        $this->invoice->addInvoiceEntry($entry);
        $this->invoice->addInvoiceEntry($entry);

        $this->assertCount(1, $this->invoice->getInvoiceEntries());
        $this->assertSame($this->invoice, $entry->getInvoice());
    }

    public function testRemoveInvoiceEntryClearsOwningSide(): void
    {
        $entry = new InvoiceEntry();
        $this->invoice->addInvoiceEntry($entry);
        $this->invoice->removeInvoiceEntry($entry);

        $this->assertCount(0, $this->invoice->getInvoiceEntries());
        $this->assertNull($entry->getInvoice());
    }

    public function testRemoveInvoiceEntryLeavesForeignOwnerAlone(): void
    {
        $other = new Invoice();
        $entry = new InvoiceEntry();
        $other->addInvoiceEntry($entry);
        $this->invoice->getInvoiceEntries()->add($entry);

        $this->invoice->removeInvoiceEntry($entry);

        $this->assertSame($other, $entry->getInvoice());
    }

    public function testSetInvoiceEntryIndexesNumbersEntriesInOrder(): void
    {
        $first = new InvoiceEntry();
        $second = new InvoiceEntry();
        $third = new InvoiceEntry();

        $this->invoice->addInvoiceEntry($first);
        $this->invoice->addInvoiceEntry($second);
        $this->invoice->addInvoiceEntry($third);

        $this->invoice->setInvoiceEntryIndexes();

        $this->assertSame(0, $first->getIndex());
        $this->assertSame(1, $second->getIndex());
        $this->assertSame(2, $third->getIndex());
    }

    public function testSetInvoiceEntryIndexesReindexesAfterRemoval(): void
    {
        $first = new InvoiceEntry();
        $second = new InvoiceEntry();
        $third = new InvoiceEntry();

        $this->invoice->addInvoiceEntry($first);
        $this->invoice->addInvoiceEntry($second);
        $this->invoice->addInvoiceEntry($third);
        $this->invoice->setInvoiceEntryIndexes();

        $this->invoice->removeInvoiceEntry($second);
        $this->invoice->setInvoiceEntryIndexes();

        $this->assertSame(0, $first->getIndex());
        $this->assertSame(1, $third->getIndex());
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->invoice, $this->invoice->setName('Invoice'));
        $this->assertSame($this->invoice, $this->invoice->setDescription(null));
        $this->assertSame($this->invoice, $this->invoice->setRecorded(false));
        $this->assertSame($this->invoice, $this->invoice->setNoCost(false));
        $this->assertSame($this->invoice, $this->invoice->addInvoiceEntry(new InvoiceEntry()));
    }
}
