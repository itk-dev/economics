<?php

namespace App\Command;

use App\Service\AnonymizeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anonymizeBefore = (new \DateTime())->sub(new \DateInterval('P5Y'));

        $count = $this->anonymizeService->anonymizeWorklogs($anonymizeBefore);

        // The command only ever runs from cron, so the count is the whole record of what it did.
        $output->writeln(sprintf('Anonymized %d worklogs started before %s.', $count, $anonymizeBefore->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
