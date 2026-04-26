<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\IssueProduct;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use App\Exception\EconomicsException;
use App\Exception\InvoiceAlreadyOnRecordException;
use App\Model\Invoices\ConfirmData;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BillingServiceTest extends TestCase
{
    private InvoiceRepository $invoiceRepository;
    private InvoiceEntryRepository $invoiceEntryRepository;
    private TranslatorInterface $translator;
    private BillingService $billingService;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepository::class);
        $this->invoiceEntryRepository = $this->createMock(InvoiceEntryRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->billingService = new BillingService(
            $this->invoiceRepository,
            $this->invoiceEntryRepository,
            $this->translator,
            '1234567890',
        );
    }

    public function testUpdateInvoiceEntryTotalPriceWorklogType(): void
    {
        $worklog1 = $this->createMock(Worklog::class);
        $worklog1->method('getTimeSpentSeconds')->willReturn(3600);

        $worklog2 = $this->createMock(Worklog::class);
        $worklog2->method('getTimeSpentSeconds')->willReturn(1800);

        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $invoiceEntry->setPrice(500.0);
        $invoiceEntry->addWorklog($worklog1);
        $invoiceEntry->addWorklog($worklog2);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->addInvoiceEntry($invoiceEntry);

        $this->invoiceEntryRepository->expects($this->once())->method('save');
        $this->invoiceRepository->expects($this->once())->method('save');

        $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

        // 3600 + 1800 = 5400 seconds = 1.5 hours
        $this->assertEqualsWithDelta(1.5, $invoiceEntry->getAmount(), 0.001);
        // 1.5 * 500 = 750
        $this->assertEqualsWithDelta(750.0, $invoiceEntry->getTotalPrice(), 0.001);
    }

    public function testUpdateInvoiceEntryTotalPriceManualType(): void
    {
        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $invoiceEntry->setAmount(10.0);
        $invoiceEntry->setPrice(200.0);

        $this->invoiceEntryRepository->expects($this->once())->method('save');

        $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

        $this->assertEqualsWithDelta(10.0, $invoiceEntry->getAmount(), 0.001);
        $this->assertEqualsWithDelta(2000.0, $invoiceEntry->getTotalPrice(), 0.001);
    }

    public function testUpdateInvoiceEntryTotalPriceNullPrice(): void
    {
        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $invoiceEntry->setAmount(10.0);
        $invoiceEntry->setPrice(null);

        $this->invoiceEntryRepository->expects($this->once())->method('save');

        $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

        $this->assertEqualsWithDelta(0.0, $invoiceEntry->getTotalPrice(), 0.001);
    }

    public function testUpdateInvoiceTotalPrice(): void
    {
        $entry1 = new InvoiceEntry();
        $entry1->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry1->setTotalPrice(100.0);

        $entry2 = new InvoiceEntry();
        $entry2->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry2->setTotalPrice(250.5);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->addInvoiceEntry($entry1);
        $invoice->addInvoiceEntry($entry2);

        $this->invoiceRepository->expects($this->once())->method('save');

        $this->billingService->updateInvoiceTotalPrice($invoice);

        $this->assertEqualsWithDelta(350.5, $invoice->getTotalPrice(), 0.001);
    }

    public function testUpdateInvoiceTotalPriceWithNullEntryPrice(): void
    {
        $entry1 = new InvoiceEntry();
        $entry1->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry1->setTotalPrice(null);

        $entry2 = new InvoiceEntry();
        $entry2->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry2->setTotalPrice(300.0);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->addInvoiceEntry($entry1);
        $invoice->addInvoiceEntry($entry2);

        $this->invoiceRepository->expects($this->once())->method('save');

        $this->billingService->updateInvoiceTotalPrice($invoice);

        $this->assertEqualsWithDelta(300.0, $invoice->getTotalPrice(), 0.001);
    }

    public function testRecordInvoiceNoConfirmation(): void
    {
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);

        $this->invoiceRepository->expects($this->never())->method('save');

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_NO);

        $this->assertFalse($invoice->isRecorded());
    }

    public function testRecordInvoiceAlreadyRecorded(): void
    {
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(true);

        $this->expectException(InvoiceAlreadyOnRecordException::class);

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES);
    }

    public function testRecordInvoiceNoClient(): void
    {
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);

        $this->expectException(EconomicsException::class);

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES);
    }

    public function testRecordInvoiceSuccess(): void
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setContact('John Doe');
        $client->setType(ClientTypeEnum::INTERNAL);
        $client->setCustomerKey('CUST001');
        $client->setEan('1234567890123');

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);

        $this->invoiceRepository->expects($this->once())->method('save');

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES);

        $this->assertTrue($invoice->isRecorded());
        $this->assertNotNull($invoice->getRecordedDate());
        $this->assertSame('INTERN', $invoice->getLockedType());
        $this->assertSame('CUST001', $invoice->getLockedCustomerKey());
        $this->assertSame('John Doe', $invoice->getLockedContactName());
        $this->assertSame('1234567890123', $invoice->getLockedEan());
        $this->assertFalse($invoice->isNoCost());
    }

    public function testRecordInvoiceExternalClient(): void
    {
        $client = new Client();
        $client->setName('External Client');
        $client->setContact('Jane Doe');
        $client->setType(ClientTypeEnum::EXTERNAL);
        $client->setCustomerKey('EXT001');

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES);

        $this->assertSame('EKSTERN', $invoice->getLockedType());
    }

    public function testRecordInvoiceNoCostConfirmation(): void
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setContact('John Doe');
        $client->setType(ClientTypeEnum::INTERNAL);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES_NO_COST);

        $this->assertTrue($invoice->isNoCost());
        $this->assertTrue($invoice->isRecorded());
    }

    public function testRecordInvoiceMarksWorklogsAsBilled(): void
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setContact('John Doe');
        $client->setType(ClientTypeEnum::INTERNAL);

        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setWorker('test@test');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime());
        $worklog->setProjectTrackerIssueId('ISSUE-1');

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $entry->setAmount(1.0);
        $entry->addWorklog($worklog);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);
        $invoice->addInvoiceEntry($entry);

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES);

        $this->assertTrue($worklog->isBilled());
        $this->assertSame(3600, $worklog->getBilledSeconds());
    }

    public function testRecordInvoiceMarksIssueProductsAsBilled(): void
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setContact('John Doe');
        $client->setType(ClientTypeEnum::INTERNAL);

        $issueProduct = new IssueProduct();

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::PRODUCT);
        $entry->setAmount(1.0);
        $entry->addIssueProduct($issueProduct);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);
        $invoice->addInvoiceEntry($entry);

        $this->billingService->recordInvoice($invoice, ConfirmData::INVOICE_RECORD_YES);

        $this->assertTrue($issueProduct->isBilled());
    }

    public function testGetInvoiceRecordableErrorsNoClient(): void
    {
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);

        $errors = $this->billingService->getInvoiceRecordableErrors($invoice);

        $this->assertNotEmpty($errors);
        $this->assertContains('invoice_recordable.error_no_client', $errors);
    }

    public function testGetInvoiceRecordableErrorsNoContact(): void
    {
        $client = new Client();
        $client->setName('Test');
        $client->setType(ClientTypeEnum::INTERNAL);
        // No contact set

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);

        $errors = $this->billingService->getInvoiceRecordableErrors($invoice);

        $this->assertContains('invoice_recordable.error_no_contact', $errors);
    }

    public function testGetInvoiceRecordableErrorsNoType(): void
    {
        $client = new Client();
        $client->setName('Test');
        $client->setContact('John');
        // No type set

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);

        $errors = $this->billingService->getInvoiceRecordableErrors($invoice);

        $this->assertContains('invoice_recordable.error_no_type', $errors);
    }

    public function testGetInvoiceRecordableErrorsEmptyEntry(): void
    {
        $client = new Client();
        $client->setName('Test');
        $client->setContact('John');
        $client->setType(ClientTypeEnum::INTERNAL);

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry->setAmount(0.0);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);
        $invoice->addInvoiceEntry($entry);

        $errors = $this->billingService->getInvoiceRecordableErrors($invoice);

        $this->assertNotEmpty($errors);
    }

    public function testGetInvoiceRecordableErrorsValidInvoice(): void
    {
        $client = new Client();
        $client->setName('Test');
        $client->setContact('John');
        $client->setType(ClientTypeEnum::INTERNAL);

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry->setAmount(5.0);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setClient($client);
        $invoice->addInvoiceEntry($entry);

        $errors = $this->billingService->getInvoiceRecordableErrors($invoice);

        $this->assertEmpty($errors);
    }

    public function testExportInvoicesToSpreadsheetRecordedInternalInvoice(): void
    {
        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry->setMaterialNumber(MaterialNumberEnum::INTERNAL);
        $entry->setProduct('Test Product');
        $entry->setAmount(10.0);
        $entry->setPrice(100.0);
        $entry->setAccount('PSP-123');

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(true);
        $invoice->setLockedType('INTERN');
        $invoice->setLockedCustomerKey('CUST001');
        $invoice->setLockedEan('');
        $invoice->setLockedContactName('John Doe');
        $invoice->setRecordedDate(new \DateTime('2024-01-15'));
        $invoice->addInvoiceEntry($entry);

        $this->invoiceRepository->method('findOneBy')
            ->willReturn($invoice);

        $spreadsheet = $this->billingService->exportInvoicesToSpreadsheet([1]);

        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        $this->assertSame('H', $sheet->getCell('A1')->getValue());
        $this->assertSame('000CUST001', $sheet->getCell('B1')->getValue());
        $this->assertSame('0020', $sheet->getCell('F1')->getValue());
        $this->assertSame(10, $sheet->getCell('G1')->getValue());
        $this->assertSame('ZIRA', $sheet->getCell('I1')->getValue());

        // Line row
        $this->assertSame('L', $sheet->getCell('A2')->getValue());
        $this->assertSame('000000000000103361', $sheet->getCell('B2')->getValue());
        $this->assertSame('Test Product', $sheet->getCell('C2')->getValue());
        $this->assertSame('10,000', $sheet->getCell('D2')->getValue());
        $this->assertSame('100,00', $sheet->getCell('E2')->getValue());
        $this->assertSame('NEJ', $sheet->getCell('F2')->getValue());
        $this->assertSame('PSP-123', $sheet->getCell('G2')->getValue());
    }

    public function testExportInvoicesToSpreadsheetRecordedExternalInvoice(): void
    {
        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry->setMaterialNumber(MaterialNumberEnum::EXTERNAL_WITH_MOMS);
        $entry->setProduct('Ext Product');
        $entry->setAmount(5.0);
        $entry->setPrice(200.0);
        $entry->setAccount('PSP-456');

        $invoice = new Invoice();
        $invoice->setName('External');
        $invoice->setRecorded(true);
        $invoice->setLockedType('EKSTERN');
        $invoice->setLockedCustomerKey('EXT001');
        $invoice->setLockedEan('1234567890123');
        $invoice->setLockedContactName('Jane Doe');
        $invoice->setRecordedDate(new \DateTime('2024-01-15'));
        $invoice->setPeriodFrom(new \DateTime('2024-01-01'));
        $invoice->setPeriodTo(new \DateTime('2024-01-31'));
        $invoice->addInvoiceEntry($entry);

        $this->invoiceRepository->method('findOneBy')
            ->willReturn($invoice);

        $spreadsheet = $this->billingService->exportInvoicesToSpreadsheet([1]);

        $sheet = $spreadsheet->getActiveSheet();

        // External-specific fields
        $this->assertSame(20, $sheet->getCell('G1')->getValue());
        $this->assertSame('ZRA', $sheet->getCell('I1')->getValue());
        $this->assertEquals('1234567890123', (string) $sheet->getCell('R1')->getValue());
        $this->assertSame('KOCIVIL', $sheet->getCell('AF1')->getValue());
    }

    public function testExportInvoicesToSpreadsheetUnrecordedInvoiceNullClient(): void
    {
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        // No client set

        $this->invoiceRepository->method('findOneBy')
            ->willReturn($invoice);

        $this->expectException(EconomicsException::class);

        $this->billingService->exportInvoicesToSpreadsheet([1]);
    }

    public function testExportInvoicesToSpreadsheetSkipsEntriesWithMissingData(): void
    {
        $entryMissingMaterial = new InvoiceEntry();
        $entryMissingMaterial->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entryMissingMaterial->setProduct('Product');
        $entryMissingMaterial->setAmount(1.0);
        $entryMissingMaterial->setPrice(100.0);
        $entryMissingMaterial->setAccount('ACC');
        // No material number

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(true);
        $invoice->setLockedType('INTERN');
        $invoice->setLockedCustomerKey('CUST001');
        $invoice->setLockedEan('');
        $invoice->setLockedContactName('John');
        $invoice->setRecordedDate(new \DateTime());
        $invoice->addInvoiceEntry($entryMissingMaterial);

        $this->invoiceRepository->method('findOneBy')
            ->willReturn($invoice);

        $spreadsheet = $this->billingService->exportInvoicesToSpreadsheet([1]);

        $sheet = $spreadsheet->getActiveSheet();

        // Only header row, no line row since entry is incomplete
        $this->assertSame('H', $sheet->getCell('A1')->getValue());
        $this->assertNull($sheet->getCell('A2')->getValue());
    }

    public function testExportInvoicesToSpreadsheetSkipsNonExistentInvoice(): void
    {
        $this->invoiceRepository->method('findOneBy')
            ->willReturn(null);

        $spreadsheet = $this->billingService->exportInvoicesToSpreadsheet([999]);

        $sheet = $spreadsheet->getActiveSheet();

        $this->assertNull($sheet->getCell('A1')->getValue());
    }

    public function testExportInvoicesToSpreadsheetInternalSupplierAccount(): void
    {
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(true);
        $invoice->setLockedType('INTERN');
        $invoice->setLockedCustomerKey('CUST001');
        $invoice->setLockedEan('');
        $invoice->setLockedContactName('John');
        $invoice->setRecordedDate(new \DateTime());

        $this->invoiceRepository->method('findOneBy')
            ->willReturn($invoice);

        $spreadsheet = $this->billingService->exportInvoicesToSpreadsheet([1]);

        $sheet = $spreadsheet->getActiveSheet();

        // Column 17 = Q = supplier account (padded to 10 chars)
        $this->assertEquals('1234567890', (string) $sheet->getCell('Q1')->getValue());
    }
}
