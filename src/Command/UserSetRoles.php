<?php

namespace App\Command;

use App\Enum\RolesEnum;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:set-roles',
    description: 'Sets the roles of a user',
)]
class UserSetRoles extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            $io->error("User not found");

            return Command::FAILURE;
        }

        $roleUser = $io->askQuestion(new ChoiceQuestion("Role user", ['y' => 'yes', 'n' => 'no'], 'n'));
        $roleAdmin = $io->askQuestion(new ChoiceQuestion("Role admin", ['y' => 'yes', 'n' => 'no'], 'n'));

        $roles = [];

        if ($roleUser === 'y') {
            $roles[] = RolesEnum::ROLE_USER->value;
        }

        if ($roleAdmin === 'y') {
            $roles[] = RolesEnum::ROLE_ADMIN->value;
        }

        $user->setRoles($roles);
        $this->userRepository->save($user, true);

        return Command::SUCCESS;
    }
}
