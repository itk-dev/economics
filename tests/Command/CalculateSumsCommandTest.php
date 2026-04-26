<?php

namespace App\Tests\Command;

use App\Command\CalculateSumsCommand;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Enum\InvoiceEntryTypeEnum;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CalculateSumsCommandTest extends TestCase
{
    public function testExecuteUpdatesAllInvoiceEntries(): void
    {
        $entry1 = new InvoiceEntry();
        $entry1->setEntryType(InvoiceEntryTypeEnum::MANUAL);

        $entry2 = new InvoiceEntry();
        $entry2->setEntryType(InvoiceEntryTypeEnum::MANUAL);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->addInvoiceEntry($entry1);
        $invoice->addInvoiceEntry($entry2);

        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->method('findAll')->willReturn([$invoice]);

        $billingService = $this->createMock(BillingService::class);
        $billingService->expects($this->exactly(2))
            ->method('updateInvoiceEntryTotalPrice');

        $command = new CalculateSumsCommand($billingService, $invoiceRepository);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteNoInvoicesReturnsSuccess(): void
    {
        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->method('findAll')->willReturn([]);

        $billingService = $this->createMock(BillingService::class);
        $billingService->expects($this->never())->method('updateInvoiceEntryTotalPrice');

        $command = new CalculateSumsCommand($billingService, $invoiceRepository);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
