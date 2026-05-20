<?php

namespace App\Tests\Integration\Repository;

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
        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $repository = $container->get(WorklogRepository::class);
        \assert($repository instanceof WorklogRepository);
        $this->repository = $repository;
        $projectRepository = $container->get(ProjectRepository::class);
        \assert($projectRepository instanceof ProjectRepository);
        $this->projectRepository = $projectRepository;
    }

    public function testFindByFilterDataBasic(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepo instanceof InvoiceEntryRepository);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertNotNull($invoiceEntry);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        $this->assertNotEmpty($result);
    }

    public function testFindByFilterDataByWorker(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepo instanceof InvoiceEntryRepository);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertNotNull($invoiceEntry);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->worker = 'admin@test.local';

        $result = $this->repository->findByFilterData($project, $invoiceEntry, $filterData);

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertStringContainsString('admin@test.local', $worklog->getWorker());
        }
    }

    public function testFindByFilterDataByDateRange(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepo instanceof InvoiceEntryRepository);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertNotNull($invoiceEntry);
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
        $this->assertNotNull($project);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepo instanceof InvoiceEntryRepository);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertNotNull($invoiceEntry);

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
        $this->assertNotNull($project);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        \assert($invoiceEntryRepo instanceof InvoiceEntryRepository);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertNotNull($invoiceEntry);

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
            'admin@test.local',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertEquals('admin@test.local', $worklog->getWorker());
        }
    }

    public function testGetTimeSpentByWorkerInWeekRangeGroupByMonth(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->getTimeSpentByWorkerInWeekRange(
            'admin@test.local',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31"),
            'month'
        );

        $this->assertNotEmpty($result);
        foreach ($result as $monthNumber => $data) {
            $this->assertArrayHasKey('totalTimeSpent', $data);
            $this->assertArrayHasKey('month', $data);
            $this->assertArrayHasKey('worker', $data);
            $this->assertEquals('admin@test.local', $data['worker']);
        }
    }

    public function testGetTimeSpentByWorkerInWeekRangeInvalidGroupBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $year = (new \DateTime())->format('Y');
        $this->repository->getTimeSpentByWorkerInWeekRange(
            'admin@test.local',
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
    }

    public function testFindBillableWorklogsByWorkerAndDateRangeFilteredByWorker(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->findBillableWorklogsByWorkerAndDateRange(
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31"),
            'admin@test.local'
        );

        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertEquals('admin@test.local', $worklog->getWorker());
        }
    }

    public function testFindBilledWorklogsByWorkerAndDateRange(): void
    {
        $year = (new \DateTime())->format('Y');
        // admin@test.local is the worker for project-0-0 (even index, so billable)
        $result = $this->repository->findBilledWorklogsByWorkerAndDateRange(
            'admin@test.local',
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        // Fixtures mark 10 worklogs as billed for project-0-0
        $this->assertNotEmpty($result);
        foreach ($result as $worklog) {
            $this->assertTrue($worklog->isBilled());
            $this->assertEquals('admin@test.local', $worklog->getWorker());
        }
    }

    public function testGetWorklogsAttachedToInvoiceInDateRange(): void
    {
        $year = (new \DateTime())->format('Y');
        $result = $this->repository->getWorklogsAttachedToInvoiceInDateRange(
            new \DateTime("$year-01-01"),
            new \DateTime("$year-12-31")
        );

        $this->assertEquals(1, $result['current_page']);
        $this->assertEquals(50, $result['page_size']);
        $this->assertGreaterThan(0, $result['total_count']);
    }
}
