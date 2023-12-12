<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calc-sums',
    description: 'Calculate sums for all invoices',
)]
class CalculateSumsCommand extends Command
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly InvoiceRepository $invoiceRepository,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $invoices = $this->invoiceRepository->findAll();

        $io->info('Updating sums for invoices.');

        $io->progressStart(count($invoices));

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $io->progressAdvance();

            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);
            }
        }

        $io->progressFinish();

        return Command::SUCCESS;
    }
}
