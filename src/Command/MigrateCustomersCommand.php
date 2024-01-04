<?php

namespace App\Command;

use App\Service\DataProviderService;
use App\Service\DataSynchronizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-customers',
    description: 'Migrate from invoice.customerAccountId to invoice.client.',
)]
class MigrateCustomersCommand extends Command
{
    public function __construct(private readonly DataSynchronizationService $dataSynchronizationService)
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($io->confirm('Are you sure?')) {
            $this->dataSynchronizationService->migrateCustomers();
        }

        $io->success('invoice.customerAccountId migrated to invoice.client');

        return Command::SUCCESS;
    }
}
