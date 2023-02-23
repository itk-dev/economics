<?php

namespace App\Command;

use App\Model\PruneUsers\UserData;
use App\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:prune-users',
    description: 'Add a short description for your command',
)]
class PruneUsersCommand extends Command
{
    public function __construct(private readonly UserService $userService)
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force removal of unwanted users.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $verbose = $input->getOption('verbose');
        $quiet = $input->getOption('quiet');

        if (!$force) {
            $io->warning('Running command without --force. No users will be anonymized.');
        }

        if (!$quiet) {
            $io->info('Finding unwanted users...');
        }

        $unwantedUsers = $this->userService->findUnwantedUsers(function ($i, $length) use ($io, $quiet) {
            if (!$quiet) {
                if (0 == $i) {
                    $io->progressStart($length);
                } elseif ($i >= $length - 1) {
                    $io->progressFinish();
                } else {
                    $io->progressAdvance();
                }
            }
        });

        if (!$quiet) {
            $io->writeln('');
            $io->info('Found: '.count($unwantedUsers).' unwanted users.');
        }

        /** @var UserData $unwantedUser */
        foreach ($unwantedUsers as $unwantedUser) {
            if (!$quiet && $verbose) {
                $io->writeln(
                    'Removing user with key: '.$unwantedUser->key.
                    ' - name: '.$unwantedUser->name
                );
            }

            if ($force) {
                $this->userService->anonymizeUser($unwantedUser->key);
            }
        }

        if (!$quiet && $verbose) {
            $io->success('Done.');
        }

        return Command::SUCCESS;
    }
}
