<?php

namespace App\Command;

use App\Service\AnonymizeService;
use App\Service\LeantimeApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:anonymize-worklogs',
    description: 'Anonymizes worklogs that are older than 5 years.',
)]
class AnonymizeWorklogsCommand extends Command
{
    public function __construct(
        private readonly AnonymizeService $anonymizeService,
    ) {
        parent::__construct($this->getName());
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anonymizeBefore = (new \DateTime())->sub(new \DateInterval('P5Y'));

        $this->anonymizeService->anonymizeWorklogs($anonymizeBefore);

        return Command::SUCCESS;
    }
}
