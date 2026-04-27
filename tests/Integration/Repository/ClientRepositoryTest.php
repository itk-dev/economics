<?php

namespace App\Tests\Integration\Repository;

use App\Model\Invoices\ClientFilterData;
use App\Repository\ClientRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ClientRepositoryTest extends KernelTestCase
{
    private ClientRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ClientRepository::class);
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new ClientFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertInstanceOf(PaginationInterface::class, $result);
        $this->assertGreaterThanOrEqual(4, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new ClientFilterData();
        $filterData->name = 'client 0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThanOrEqual(2, $result->getTotalItemCount());
        foreach ($result as $client) {
            $this->assertStringContainsString('client 0', $client->getName());
        }
    }

    public function testGetFilteredPaginationByContact(): void
    {
        $filterData = new ClientFilterData();
        $filterData->contact = 'Kontakt Kontaktesen 0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $client) {
            $this->assertStringContainsString('Kontakt Kontaktesen 0', $client->getContact());
        }
    }
}
