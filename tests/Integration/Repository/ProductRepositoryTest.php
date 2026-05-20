<?php

namespace App\Tests\Integration\Repository;

use App\Model\Invoices\ProductFilterData;
use App\Repository\ProductRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductRepositoryTest extends KernelTestCase
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(ProductRepository::class);
        \assert($repository instanceof ProductRepository);
        $this->repository = $repository;
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new ProductFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(3, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new ProductFilterData();
        $filterData->name = 'Alpha';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $product) {
            $this->assertStringContainsString('Alpha', $product->getName());
        }
    }

    public function testGetFilteredPaginationByProject(): void
    {
        $projectRepo = self::getContainer()->get(ProjectRepository::class);
        \assert($projectRepo instanceof ProjectRepository);
        $project = $projectRepo->findOneBy(['name' => 'project-0-0']);

        $filterData = new ProductFilterData();
        $filterData->project = $project;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertEquals(2, $result->getTotalItemCount());
        foreach ($result as $product) {
            $this->assertEquals($project->getId(), $product->getProject()->getId());
        }
    }
}
