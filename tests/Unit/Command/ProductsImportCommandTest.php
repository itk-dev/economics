<?php

namespace App\Tests\Unit\Command;

use App\Command\ProductsImportCommand;
use App\Entity\Product;
use App\Entity\Project;
use App\Repository\ProductRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class ProductsImportCommandTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    public function testUnreadableFileThrows(): void
    {
        $tester = new CommandTester($this->createCommand());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read file');

        $tester->execute(['filename' => '/does/not/exist.csv']);
    }

    public function testMissingNameHeaderFails(): void
    {
        $file = $this->csv("title,price,project.id\nProduct Alpha,100,1\n");

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Header name not found', $tester->getDisplay());
    }

    public function testMismatchedCellCountFails(): void
    {
        $file = $this->csv("name,price,project.id\nProduct Alpha,100\n");

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Expected 3', $tester->getDisplay());
    }

    public function testUnknownProjectIdFails(): void
    {
        $file = $this->csv("name,price,project.id\nProduct Alpha,100,999\n");

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('find')->with(999)->willReturn(null);

        $tester = new CommandTester($this->createCommand(projectRepository: $projectRepository));
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid project id: 999', $tester->getDisplay());
    }

    public function testNewProductIsPersisted(): void
    {
        $file = $this->csv("name,price,project.id\nProduct Alpha,100.50,1\n");

        $project = new Project();
        $project->setName('Economics');

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('find')->with(1)->willReturn($project);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('findOneBy')->with(['name' => 'Product Alpha'])->willReturn(null);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('contains')->willReturn(false);
        $entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (Product $product) use (&$persisted): void {
                $persisted = $product;
            });
        $entityManager->expects($this->once())->method('flush');

        $tester = new CommandTester($this->createCommand($productRepository, $projectRepository, $entityManager));
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertInstanceOf(Product::class, $persisted);
        $this->assertSame('Product Alpha', $persisted->getName());
        $this->assertSame('100.50', $persisted->getPrice());
        $this->assertSame($project, $persisted->getProject());
    }

    public function testExistingProductIsUpdatedInPlace(): void
    {
        $file = $this->csv("name,price,project.id\nProduct Alpha,250,1\n");

        $project = new Project();
        $project->setName('Economics');

        $existing = new Product();
        $existing->setName('Product Alpha');
        $existing->setPrice('100');

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('find')->willReturn($project);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('findOneBy')->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('contains')->willReturn(true);
        $entityManager->expects($this->once())->method('persist')->with($existing);

        $tester = new CommandTester($this->createCommand($productRepository, $projectRepository, $entityManager));
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('250', $existing->getPrice());
        $this->assertSame($project, $existing->getProject());
    }

    public function testEveryRowIsImported(): void
    {
        $file = $this->csv("name,price,project.id\nProduct Alpha,100,1\nProduct Beta,200,1\nProduct Gamma,300,1\n");

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('find')->willReturn(new Project());

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('contains')->willReturn(false);
        $entityManager->expects($this->exactly(3))->method('persist');
        $entityManager->expects($this->exactly(3))->method('flush');

        $tester = new CommandTester($this->createCommand($productRepository, $projectRepository, $entityManager));
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testHeaderOnlyFileSucceedsWithoutImporting(): void
    {
        $file = $this->csv("name,price,project.id\n");

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $tester = new CommandTester($this->createCommand(entityManager: $entityManager));
        $tester->execute(['filename' => $file]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    private function createCommand(
        ?ProductRepository $productRepository = null,
        ?ProjectRepository $projectRepository = null,
        ?EntityManagerInterface $entityManager = null,
    ): ProductsImportCommand {
        return new ProductsImportCommand(
            $productRepository ?? $this->createMock(ProductRepository::class),
            $projectRepository ?? $this->createMock(ProjectRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
        );
    }

    private function csv(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'products');
        $this->assertIsString($file, 'Could not create a temporary import file.');

        file_put_contents($file, $contents);
        $this->tempFiles[] = $file;

        return $file;
    }
}
