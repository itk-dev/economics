<?php

namespace App\Tests\Integration\Repository;

use App\Model\Invoices\NameFilterData;
use App\Repository\WorkerGroupRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorkerGroupRepositoryTest extends KernelTestCase
{
    private WorkerGroupRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(WorkerGroupRepository::class);
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new NameFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertInstanceOf(PaginationInterface::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new NameFilterData();
        $filterData->name = 'Alpha';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertEquals(1, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByNameNoMatch(): void
    {
        $filterData = new NameFilterData();
        $filterData->name = 'Nonexistent Group';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertEquals(0, $result->getTotalItemCount());
    }
}
