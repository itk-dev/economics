<?php

namespace App\Tests\Integration\Repository;

use App\Enum\HostingProviderEnum;
use App\Model\Invoices\ServiceAgreementFilterData;
use App\Repository\ServiceAgreementRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ServiceAgreementRepositoryTest extends KernelTestCase
{
    private ServiceAgreementRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(ServiceAgreementRepository::class);
        \assert($repository instanceof ServiceAgreementRepository);
        $this->repository = $repository;
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(3, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByProject(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $filterData->project = 'project-0-0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByClient(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $filterData->client = 'client 0-0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByCybersecurityAgreementTrue(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $filterData->cybersecurityAgreement = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByCybersecurityAgreementFalse(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $filterData->cybersecurityAgreement = false;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByHostingProvider(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $filterData->hostingProvider = HostingProviderEnum::ADM;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByActive(): void
    {
        $filterData = new ServiceAgreementFilterData();
        $filterData->active = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
    }
}
