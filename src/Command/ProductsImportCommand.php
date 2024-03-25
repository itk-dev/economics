<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:products:import',
    description: 'Import products from a CSV file',
)]
class ProductsImportCommand extends Command
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filename', InputArgument::REQUIRED, 'The import filename. Must contain a "name" column.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = $input->getArgument('filename');
        if (!is_readable($filename)) {
            throw new RuntimeException(sprintf('Cannot read file %s', $filename));
        }

        $headerName = 'name';

        $getRowAsStrings = static fn (Row $row) => array_map(
            static fn ($value) => match (true) {
                $value instanceof \DateTimeInterface,
                $value instanceof \DateInterval => throw new RuntimeException(sprintf('Unexpected type: %s', $value::class)),
                default => (string) $value
            },
            $row->toArray()
        );
        $reader = new Reader();
        $reader->open($filename);
        foreach ($reader->getSheetIterator() as $sheet) {
            $headers = null;
            foreach ($sheet->getRowIterator() as $row) {
                if (null === $row) {
                    continue;
                }
                if (null === $headers) {
                    $headers = $getRowAsStrings($row);
                    if (!in_array($headerName, $headers)) {
                        $io->error(sprintf('Header %s not found', $headerName));

                        return Command::FAILURE;
                    }
                    continue;
                }
                if (count($headers) !== $row->getNumCells()) {
                    $io->error(sprintf('Found %d cells. Expected %d in %s',
                        $row->getNumCells(),
                        count($headers),
                        json_encode($row->toArray())
                    ));

                    return Command::FAILURE;
                }
                $values = array_combine(
                    $headers,
                    $getRowAsStrings($row),
                );

                $name = (string) $values[$headerName];

                $projectId = (int) $values['project.id'];
                $project = $this->projectRepository->find($projectId);
                if (null === $project) {
                    $io->error(sprintf('Invalid project id: %d', $projectId));

                    return Command::FAILURE;
                }

                $product = $this->productRepository->findOneBy(['name' => $name]) ?? new Product();
                $isNew = $this->entityManager->contains($product);
                $product
                    ->setName($name)
                    ->setPrice((string) $values['price'])
                    ->setProject($project);
                $this->entityManager->persist($product);
                $io->definitionList(
                    ['status' => $isNew ? 'created' : 'updated'],
                    ['name' => $product->getName()],
                    ['price' => $product->getPrice()],
                    ['project' => $product->getProject()?->getName()]
                );

                // We're less concerned with performance than with duplicate products.
                $this->entityManager->flush();
            }
        }
        $reader->close();

        return Command::SUCCESS;
    }
}
