<?php

namespace App\Tests\Integration\Repository;

use App\Model\Invoices\NameFilterData;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AccountRepositoryTest extends KernelTestCase
{
    private AccountRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(AccountRepository::class);
        \assert($repository instanceof AccountRepository);
        $this->repository = $repository;
    }

    public function testGetAllChoices(): void
    {
        $result = $this->repository->getAllChoices();

        $this->assertGreaterThanOrEqual(2, \count($result));

        $this->assertArrayHasKey('ACC001: Test Account 1', $result);
        $this->assertEquals('ACC001', $result['ACC001: Test Account 1']);

        $this->assertArrayHasKey('ACC002: Test Account 2', $result);
        $this->assertEquals('ACC002', $result['ACC002: Test Account 2']);
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new NameFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new NameFilterData();
        $filterData->name = 'Test Account 1';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
    }
}
