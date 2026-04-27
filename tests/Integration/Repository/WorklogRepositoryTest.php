<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Worklog;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\InvoiceEntryRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorklogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorklogRepository $repository;
    private ProjectRepository $projectRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(WorklogRepository::class);
        $this->projectRepository = $container->get(ProjectRepository::class);
    }

    public function testFindByFilterDataBasic(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertInstanceOf(Worklog::class, $worklog);
        }
    }

    public function testFindByFilterDataByWorker(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->worker = 'test0@test';

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertStringContainsString('test0@test', $worklog->getWorker());
        }
    }

    public function testFindByFilterDataByDateRange(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $year = (new \DateTime())->format('Y');

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->periodFrom = new \DateTime("$year-01-01");
        $filterData->periodTo = new \DateTime("$year-01-31");

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertGreaterThanOrEqual(
                new \DateTime("$year-01-01"),
                $worklog->getStarted()
            );
        }
    }

    public function testFindByFilterDataByBilled(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->isBilled = true;

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertTrue($worklog->isBilled());
        }
    }

    public function testFindByFilterDataOnlyAvailable(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = true;

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        foreach ($result as $worklog) {
            $entry = $worklog->getInvoiceEntry();
            $this->assertTrue(
                null === $entry || $entry->getId() === $invoiceEntry->getId(),
                'Worklog should have no invoice entry or match the provided entry'
            );
        }
    }

    public function testFindWorklogsByWorkerAndDateRange(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->findWorklogsByWorkerAndDateRange(
            'test0@test',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertEquals('test0@test', $worklog->getWorker());
        }
    }

    public function testGetTimeSpentByWorkerInWeekRangeGroupByMonth(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->getTimeSpentByWorkerInWeekRange(
            'test0@test',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31"),
            'month'
        );

        $this->assertNotEmpty($result);
        foreach ($result as $monthNumber => $data) {
            $this->assertArrayHasKey('totalTimeSpent', $data);
            $this->assertArrayHasKey('month', $data);
            $this->assertArrayHasKey('worker', $data);
            $this->assertEquals('test0@test', $data['worker']);
        }
    }

    public function testGetTimeSpentByWorkerInWeekRangeInvalidGroupBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $year = (new \DateTime())->format('Y');
        $this->repository->getTimeSpentByWorkerInWeekRange(
            'test0@test',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31"),
            'invalid'
        );
    }

    public function testFindBillableWorklogsByWorkerAndDateRange(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->findBillableWorklogsByWorkerAndDateRange(
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertInstanceOf(Worklog::class, $worklog);
        }
    }

    public function testFindBillableWorklogsByWorkerAndDateRangeFilteredByWorker(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->findBillableWorklogsByWorkerAndDateRange(
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31"),
            'test0@test'
        );

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertEquals('test0@test', $worklog->getWorker());
        }
    }

    public function testFindBilledWorklogsByWorkerAndDateRange(): void
    {
        $year = (new \DateTime())->format('Y');
        // test0@test is the worker for project-0-0 (even index, so billable)
        $result = $this->repository->findBilledWorklogsByWorkerAndDateRange(
            'test0@test',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        // Fixtures mark 10 worklogs as billed for project-0-0
        foreach ($result as $worklog) {
            $this->assertTrue($worklog->isBilled());
            $this->assertEquals('test0@test', $worklog->getWorker());
        }
    }

    public function testGetWorklogsAttachedToInvoiceInDateRange(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->getWorklogsAttachedToInvoiceInDateRange(
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_count', $result);
        $this->assertArrayHasKey('pages_count', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('page_size', $result);
        $this->assertArrayHasKey('paginator', $result);
        $this->assertEquals(1, $result['current_page']);
        $this->assertEquals(50, $result['page_size']);
        $this->assertGreaterThan(0, $result['total_count']);
    }
}
