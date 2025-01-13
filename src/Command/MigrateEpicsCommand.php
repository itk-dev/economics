<?php

namespace App\Command;

use App\Entity\Epic;
use App\Entity\Issue;
use App\Service\DataSynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-epics',
    description: 'Migrate epics for existing legacy issues, create the epics and add link to join table.',
)]
class MigrateEpicsCommand extends Command
{
    public function __construct(
        private DataSynchronizationService $dataSynchronizationService,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->dataSynchronizationService->migrateEpics(function ($i, $length) use ($io) {
            if (0 == $i) {
                $io->progressStart($length);
            } elseif ($i >= $length - 1) {
                $io->progressFinish();
            } else {
                $io->progressAdvance();
            }
        });

        $io->success('All issues have been processed successfully.');

        return Command::SUCCESS;
    }
}
