<?php

namespace App\Command;

use App\Entity\DataProvider;
use App\Repository\DataProviderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data-provider:set-enable',
    description: 'Enable/disable a data provider',
)]
class DataProviderSetEnableCommand extends Command
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'data provider id');
        $this->addArgument('enable', InputArgument::REQUIRED, 'data provider enable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = (int) $input->getArgument('id');
        $enable = $input->getArgument('enable');

        $dataProvider = $this->dataProviderRepository->find($id);

        if ($dataProvider != null) {
            $dataProvider->setEnabled($enable == 'true');
            $this->dataProviderRepository->save($dataProvider, true);
        }

        $io->info("Data provider with id: " . $dataProvider->getId() . " " . ($dataProvider->isEnabled() ? 'enabled' : 'disabled'));

        return Command::SUCCESS;
    }
}
