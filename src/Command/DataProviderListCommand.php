<?php

namespace App\Command;

use App\Entity\DataProvider;
use App\Repository\DataProviderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data-provider:list',
    description: 'List data providers',
)]
class DataProviderListCommand extends Command
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dataProviders = $this->dataProviderRepository->findAll();

        /* @var DataProvider $provider */
        $io->table(['id', 'name', 'enabled', 'class', 'url', 'sync clients', 'sync accounts'], array_map(fn ($provider) => [
            $provider->getId(),
            $provider->getName(),
            $provider->isEnabled() ? 'true' : 'false',
            $provider->getClass(),
            $provider->getUrl(),
            $provider->isEnableClientSync(),
            $provider->isEnableAccountSync(),
        ], $dataProviders));

        return Command::SUCCESS;
    }
}
