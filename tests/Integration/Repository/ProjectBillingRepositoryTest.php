<?php

namespace App\Tests\Integration\Repository;

use App\Model\Invoices\ProjectBillingFilterData;
use App\Repository\ProjectBillingRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectBillingRepositoryTest extends KernelTestCase
{
    private ProjectBillingRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ProjectBillingRepository::class);
    }

    public function testGetFilteredPaginationRecorded(): void
    {
        $filterData = new ProjectBillingFilterData();
        $filterData->recorded = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertInstanceOf(PaginationInterface::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $pb) {
            $this->assertTrue($pb->isRecorded());
        }
    }

    public function testGetFilteredPaginationUnrecorded(): void
    {
        $filterData = new ProjectBillingFilterData();
        $filterData->recorded = false;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $pb) {
            $this->assertFalse($pb->isRecorded());
        }
    }

    public function testGetFilteredPaginationByCreatedBy(): void
    {
        $filterData = new ProjectBillingFilterData();
        $filterData->recorded = false;
        $filterData->createdBy = 'nonexistent-user@test';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertEquals(0, $result->getTotalItemCount());
    }
}
