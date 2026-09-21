<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\IssueProduct;
use App\Entity\Worklog;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use PHPUnit\Framework\TestCase;

class InvoiceEntryTest extends TestCase
{
    private InvoiceEntry $entry;

    protected function setUp(): void
    {
        $this->entry = new InvoiceEntry();
    }

    public function testCollectionsStartEmpty(): void
    {
        $this->assertCount(0, $this->entry->getWorklogs());
        $this->assertCount(0, $this->entry->getIssueProducts());
    }

    public function testScalarAccessors(): void
    {
        $this->entry->setIndex(3);
        $this->entry->setDescription('Consulting');
        $this->entry->setAccount('1234');
        $this->entry->setProduct('Product Alpha');
        $this->entry->setPrice(950.0);
        $this->entry->setAmount(7.5);
        $this->entry->setTotalPrice(7125.0);
        $this->entry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $this->entry->setMaterialNumber(MaterialNumberEnum::EXTERNAL_WITH_MOMS);

        $this->assertSame(3, $this->entry->getIndex());
        $this->assertSame('Consulting', $this->entry->getDescription());
        $this->assertSame('1234', $this->entry->getAccount());
        $this->assertSame('Product Alpha', $this->entry->getProduct());
        $this->assertSame(950.0, $this->entry->getPrice());
        $this->assertSame(7.5, $this->entry->getAmount());
        $this->assertSame(7125.0, $this->entry->getTotalPrice());
        $this->assertSame(InvoiceEntryTypeEnum::WORKLOG, $this->entry->getEntryType());
        $this->assertSame(MaterialNumberEnum::EXTERNAL_WITH_MOMS, $this->entry->getMaterialNumber());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->entry->setDescription(null);
        $this->entry->setAccount(null);
        $this->entry->setProduct(null);
        $this->entry->setPrice(null);
        $this->entry->setAmount(null);
        $this->entry->setTotalPrice(null);
        $this->entry->setMaterialNumber(null);
        $this->entry->setInvoice(null);

        $this->assertNull($this->entry->getDescription());
        $this->assertNull($this->entry->getAccount());
        $this->assertNull($this->entry->getProduct());
        $this->assertNull($this->entry->getPrice());
        $this->assertNull($this->entry->getAmount());
        $this->assertNull($this->entry->getTotalPrice());
        $this->assertNull($this->entry->getMaterialNumber());
        $this->assertNull($this->entry->getInvoice());
    }

    public function testAddWorklogSetsOwningSide(): void
    {
        $worklog = new Worklog();
        $this->entry->addWorklog($worklog);
        $this->entry->addWorklog($worklog);

        $this->assertCount(1, $this->entry->getWorklogs());
        $this->assertSame($this->entry, $worklog->getInvoiceEntry());
    }

    public function testRemoveWorklogClearsOwningSide(): void
    {
        $worklog = new Worklog();
        $this->entry->addWorklog($worklog);
        $this->entry->removeWorklog($worklog);

        $this->assertCount(0, $this->entry->getWorklogs());
        $this->assertNull($worklog->getInvoiceEntry());
    }

    public function testRemoveWorklogLeavesForeignOwnerAlone(): void
    {
        $other = new InvoiceEntry();
        $worklog = new Worklog();
        $other->addWorklog($worklog);
        $this->entry->getWorklogs()->add($worklog);

        $this->entry->removeWorklog($worklog);

        $this->assertSame($other, $worklog->getInvoiceEntry());
    }

    public function testAddIssueProductSetsOwningSide(): void
    {
        $issueProduct = new IssueProduct();
        $this->entry->addIssueProduct($issueProduct);
        $this->entry->addIssueProduct($issueProduct);

        $this->assertCount(1, $this->entry->getIssueProducts());
        $this->assertSame($this->entry, $issueProduct->getInvoiceEntry());
    }

    public function testRemoveIssueProductClearsOwningSide(): void
    {
        $issueProduct = new IssueProduct();
        $this->entry->addIssueProduct($issueProduct);
        $this->entry->removeIssueProduct($issueProduct);

        $this->assertCount(0, $this->entry->getIssueProducts());
        $this->assertNull($issueProduct->getInvoiceEntry());
    }

    public function testRemoveIssueProductLeavesForeignOwnerAlone(): void
    {
        $other = new InvoiceEntry();
        $issueProduct = new IssueProduct();
        $other->addIssueProduct($issueProduct);
        $this->entry->getIssueProducts()->add($issueProduct);

        $this->entry->removeIssueProduct($issueProduct);

        $this->assertSame($other, $issueProduct->getInvoiceEntry());
    }

    public function testSetInvoiceIndexReindexesTheOwningInvoice(): void
    {
        $invoice = new Invoice();
        $first = new InvoiceEntry();
        $invoice->addInvoiceEntry($first);
        $invoice->addInvoiceEntry($this->entry);

        $this->entry->setInvoiceIndex();

        $this->assertSame(0, $first->getIndex());
        $this->assertSame(1, $this->entry->getIndex());
    }

    public function testSetInvoiceIndexIsANoopWithoutAnInvoice(): void
    {
        $this->entry->setInvoiceIndex();

        $this->assertNull($this->entry->getIndex());
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->entry, $this->entry->setIndex(0));
        $this->assertSame($this->entry, $this->entry->setInvoice(new Invoice()));
        $this->assertSame($this->entry, $this->entry->setEntryType(InvoiceEntryTypeEnum::MANUAL));
        $this->assertSame($this->entry, $this->entry->addWorklog(new Worklog()));
        $this->assertSame($this->entry, $this->entry->addIssueProduct(new IssueProduct()));
    }
}
