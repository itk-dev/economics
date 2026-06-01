<?php

namespace App\Tests\Integration\Repository;

use App\Model\Invoices\InvoiceFilterData;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InvoiceRepositoryTest extends KernelTestCase
{
    private InvoiceRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(InvoiceRepository::class);
        \assert($repository instanceof InvoiceRepository);
        $this->repository = $repository;
    }

    public function testGetByRecordedDateBetween(): void
    {
        $from = new \DateTime('-3 months');
        $to = new \DateTime('now');

        $result = $this->repository->getByRecordedDateBetween($from, $to);

        $this->assertNotEmpty($result);
        foreach ($result as $invoice) {
            $this->assertTrue($invoice->isRecorded());
            $this->assertNotNull($invoice->getRecordedDate());
        }
    }

    public function testGetByRecordedDateBetweenNoResults(): void
    {
        $from = new \DateTime('2000-01-01');
        $to = new \DateTime('2000-12-31');

        $result = $this->repository->getByRecordedDateBetween($from, $to);

        $this->assertEmpty($result);
    }

    public function testGetFilteredPaginationRecorded(): void
    {
        $filterData = new InvoiceFilterData();
        $filterData->recorded = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
        foreach ($result as $invoice) {
            $this->assertTrue($invoice->isRecorded());
        }
    }

    public function testGetFilteredPaginationUnrecorded(): void
    {
        $filterData = new InvoiceFilterData();
        $filterData->recorded = false;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $invoice) {
            $this->assertFalse($invoice->isRecorded());
        }
    }

    public function testGetFilteredPaginationByQuery(): void
    {
        $filterData = new InvoiceFilterData();
        $filterData->recorded = false;
        $filterData->query = 'Invoice Beta';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $invoice) {
            $this->assertStringContainsString('Invoice Beta', (string) $invoice->getName());
        }
    }

    public function testGetFilteredPaginationByNoCost(): void
    {
        $filterData = new InvoiceFilterData();
        $filterData->recorded = true;
        $filterData->noCost = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $invoice) {
            $this->assertTrue($invoice->isNoCost());
        }
    }

    public function testGetFilteredPaginationByProjectBilling(): void
    {
        $filterData = new InvoiceFilterData();
        $filterData->recorded = false;
        $filterData->projectBilling = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(1, $result->getTotalItemCount());
        foreach ($result as $invoice) {
            $this->assertNotNull($invoice->getProjectBilling());
        }
    }
}
