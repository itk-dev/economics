<?php

namespace App\Tests\Integration\Repository;

use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\InvoiceEntryRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorklogRepositoryTest extends KernelTestCase
{
    private WorklogRepository $repository;
    private ProjectRepository $projectRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
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

    public function testSumSelectableTimeSpentSecondsByFilterDataMatchesSelectableWorklogs(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertInstanceOf(Project::class, $project);
        $this->assertInstanceOf(InvoiceEntry::class, $invoiceEntry);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->worker = 'admin@test.local';

        $expected = $this->sumSelectable($project, $invoiceEntry, $filterData);

        $this->assertGreaterThan(0, $expected);
        $this->assertSame(
            $expected,
            $this->repository->sumSelectableTimeSpentSecondsByFilterData($project, $invoiceEntry, $filterData)
        );
    }

    public function testSumSelectableTimeSpentSecondsByFilterDataWithVersionFilter(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertInstanceOf(Project::class, $project);
        $this->assertInstanceOf(InvoiceEntry::class, $invoiceEntry);

        $version = $project->getVersions()->first();
        $this->assertInstanceOf(Version::class, $version);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->version = $version;

        $expected = $this->sumSelectable($project, $invoiceEntry, $filterData);

        $this->assertGreaterThan(0, $expected);
        $this->assertSame(
            $expected,
            $this->repository->sumSelectableTimeSpentSecondsByFilterData($project, $invoiceEntry, $filterData)
        );
    }

    public function testSumSelectableTimeSpentSecondsByFilterDataExcludesBilledWorklogs(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertInstanceOf(Project::class, $project);
        $this->assertInstanceOf(InvoiceEntry::class, $invoiceEntry);

        $filterData = new InvoiceEntryWorklogsFilterData();

        $listed = 0;
        $billed = 0;
        foreach ($this->repository->findByFilterData($project, $invoiceEntry, $filterData) as $worklog) {
            $this->assertInstanceOf(Worklog::class, $worklog);
            $listed += (int) $worklog->getTimeSpentSeconds();

            if ($worklog->isBilled()) {
                $billed += (int) $worklog->getTimeSpentSeconds();
            }
        }

        // The default filter lists billed worklogs, which the picker renders
        // without a checkbox, so the total must not include them.
        $this->assertGreaterThan(0, $billed);
        $this->assertSame(
            $listed - $billed,
            $this->repository->sumSelectableTimeSpentSecondsByFilterData($project, $invoiceEntry, $filterData)
        );
    }

    public function testSumSelectableTimeSpentSecondsByFilterDataExcludesWorklogsHeldByAnotherEntry(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        [$otherEntry, $invoiceEntry] = $invoiceEntryRepo->findBy([], ['id' => 'ASC'], 2);
        $this->assertInstanceOf(Project::class, $project);
        $this->assertInstanceOf(InvoiceEntry::class, $otherEntry);
        $this->assertInstanceOf(InvoiceEntry::class, $invoiceEntry);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;

        $listed = 0;
        $notSelectable = 0;
        $unbilledHeldByOther = 0;
        foreach ($this->repository->findByFilterData($project, $invoiceEntry, $filterData) as $worklog) {
            $this->assertInstanceOf(Worklog::class, $worklog);
            $seconds = (int) $worklog->getTimeSpentSeconds();
            $listed += $seconds;

            $owner = $worklog->getInvoiceEntry();
            $heldByOther = null !== $owner && $owner->getId() !== $invoiceEntry->getId();

            if ($worklog->isBilled() || $heldByOther) {
                $notSelectable += $seconds;
            }

            if (!$worklog->isBilled() && $owner?->getId() === $otherEntry->getId()) {
                $unbilledHeldByOther += $seconds;
            }
        }

        // Guard the point of the test: without unbilled worklogs on another
        // entry, the held-by-another-entry exclusion would pass untested.
        $this->assertGreaterThan(0, $unbilledHeldByOther, 'Expected unbilled worklogs held by another invoice entry in fixtures.');
        $this->assertSame(
            $listed - $notSelectable,
            $this->repository->sumSelectableTimeSpentSecondsByFilterData($project, $invoiceEntry, $filterData)
        );
    }

    public function testSumSelectableTimeSpentSecondsByFilterDataIsZeroWhenNothingMatches(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $invoiceEntryRepo = self::getContainer()->get(InvoiceEntryRepository::class);
        $invoiceEntry = $invoiceEntryRepo->findOneBy([], ['id' => 'ASC']);
        $this->assertInstanceOf(Project::class, $project);
        $this->assertInstanceOf(InvoiceEntry::class, $invoiceEntry);

        $filterData = new InvoiceEntryWorklogsFilterData();
        $filterData->onlyAvailable = false;
        $filterData->worker = 'no-such-worker@test.local';

        $this->assertSame(0, $this->repository->sumSelectableTimeSpentSecondsByFilterData($project, $invoiceEntry, $filterData));
    }

    /**
     * Sum the listed worklogs the picker offers a checkbox for, mirroring the
     * disabled condition in invoice_entry/worklogs.html.twig.
     */
    private function sumSelectable(Project $project, InvoiceEntry $invoiceEntry, InvoiceEntryWorklogsFilterData $filterData): int
    {
        $sum = 0;

        foreach ($this->repository->findByFilterData($project, $invoiceEntry, $filterData) as $worklog) {
            $this->assertInstanceOf(Worklog::class, $worklog);

            $owner = $worklog->getInvoiceEntry();

            if ($worklog->isBilled() || (null !== $owner && $owner->getId() !== $invoiceEntry->getId())) {
                continue;
            }

            $sum += (int) $worklog->getTimeSpentSeconds();
        }

        return $sum;
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

    public function testGetWorklogsByIssueAndPeriodReturnsAllWhenNoDates(): void
    {
        $issueRepository = self::getContainer()->get(IssueRepository::class);
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $issue = $issueRepository->findOneBy(['project' => $project], ['id' => 'ASC']);

        $result = $this->repository->getWorklogsByIssueAndPeriod($issue->getId(), null, null);

        // Fixtures attach 100 worklogs per issue.
        $this->assertCount(100, $result);
        foreach ($result as $worklog) {
            $this->assertInstanceOf(Worklog::class, $worklog);
            $this->assertSame($issue->getId(), $worklog->getIssue()->getId());
        }
    }

    public function testGetWorklogsByIssueAndPeriodFiltersByPeriod(): void
    {
        $issueRepository = self::getContainer()->get(IssueRepository::class);
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $issue = $issueRepository->findOneBy(['project' => $project], ['id' => 'ASC']);

        $year = (new \DateTime())->format('Y');

        // Fixture worklogs use started = "$year-{(k%12)+1}-{(k%28)+1}", so
        // limiting to January should match worklogs where (k % 12) == 0, i.e.
        // k ∈ {0,12,24,36,48,60,72,84,96} — 9 worklogs.
        $result = $this->repository->getWorklogsByIssueAndPeriod(
            $issue->getId(),
            new \DateTime("$year-01-01"),
            new \DateTime("$year-01-31"),
        );

        $this->assertCount(9, $result);
        foreach ($result as $worklog) {
            $this->assertSame('01', $worklog->getStarted()->format('m'));
        }
    }

    public function testGetWorklogsByIssueAndPeriodEmptyForUnknownIssue(): void
    {
        $result = $this->repository->getWorklogsByIssueAndPeriod(999999999, null, null);

        $this->assertSame([], $result);
    }

    public function testGetWorklogsByIssueAndPeriodReturnsOrderedByStarted(): void
    {
        $issueRepository = self::getContainer()->get(IssueRepository::class);
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $issue = $issueRepository->findOneBy(['project' => $project], ['id' => 'ASC']);

        $result = $this->repository->getWorklogsByIssueAndPeriod($issue->getId(), null, null);

        $previous = null;
        foreach ($result as $worklog) {
            if (null !== $previous) {
                $this->assertGreaterThanOrEqual(
                    $previous->getTimestamp(),
                    $worklog->getStarted()->getTimestamp(),
                    'Worklogs should be returned ordered by started ASC.'
                );
            }
            $previous = $worklog->getStarted();
        }
    }
}
