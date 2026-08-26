<?php

namespace App\Tests\Integration\Repository;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\InvoiceEntryRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorklogRepositoryTest extends KernelTestCase
{
    /**
     * The anonymization tests assert exact affected-row counts, so they work in a window well
     * below the worklogs AppFixtures ages for app:anonymize-worklogs. Nothing but the rows a test
     * creates itself starts before self::CUTOFF.
     */
    private const OLD_ENOUGH = '-30 years';
    private const CUTOFF = '-20 years';

    private EntityManagerInterface $entityManager;
    private WorklogRepository $repository;
    private ProjectRepository $projectRepository;

    /** @var list<int> */
    private array $createdWorklogIds = [];
    /** @var list<int> */
    private array $createdIssueIds = [];
    /** @var list<int> */
    private array $createdProjectIds = [];
    /** @var list<int> */
    private array $createdDataProviderIds = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(WorklogRepository::class);
        $this->projectRepository = $container->get(ProjectRepository::class);
    }

    protected function tearDown(): void
    {
        $this->removeCreated(Worklog::class, $this->createdWorklogIds);
        $this->removeCreated(Issue::class, $this->createdIssueIds);
        $this->removeCreated(Project::class, $this->createdProjectIds);
        $this->removeCreated(DataProvider::class, $this->createdDataProviderIds);

        parent::tearDown();
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

    public function testFixturesIncludeWorklogsTheAnonymizeCommandCanActOn(): void
    {
        // AppFixtures stamps every worklog with the current year except a handful on project-1-9,
        // which it ages past the command's five year window on purpose.
        $anonymizable = $this->repository->matching(
            Criteria::create()
                ->where(Criteria::expr()->lt('started', new \DateTime('-5 years')))
                ->andWhere(Criteria::expr()->isNull('anonymizedDate'))
        );

        $this->assertCount(3, $anonymizable);
    }

    public function testAnonymizeWorklogsBeforeDate(): void
    {
        $oldId = $this->persistWorklog('before-old', new \DateTime(self::OLD_ENOUGH));
        $recentId = $this->persistWorklog('before-recent', new \DateTime('-1 year'));

        $affectedRows = $this->repository->anonymizeWorklogs(new \DateTime(self::CUTOFF));

        $this->assertSame(1, $affectedRows);

        $this->entityManager->clear();

        $old = $this->findWorklog($oldId);
        $this->assertSame('worklog '.$oldId, $old->getDescription());
        $this->assertNotNull($old->getAnonymizedDate());

        $recent = $this->findWorklog($recentId);
        $this->assertSame('Description of worklog before-recent', $recent->getDescription());
        $this->assertNull($recent->getAnonymizedDate());
    }

    public function testAnonymizeWorklogsWithNoMatchingRecords(): void
    {
        // No fixture worklog, aged or not, starts anywhere near this.
        $affectedRows = $this->repository->anonymizeWorklogs(new \DateTime('1900-01-01'));

        $this->assertSame(0, $affectedRows);
    }

    public function testAnonymizeWorklogsSetsAnonymizedDateToNow(): void
    {
        $worklogId = $this->persistWorklog('sets-date', new \DateTime(self::OLD_ENOUGH));

        $before = new \DateTime();
        $this->repository->anonymizeWorklogs(new \DateTime(self::CUTOFF));
        $after = new \DateTime();

        $this->entityManager->clear();

        $anonymizedDate = $this->findWorklog($worklogId)->getAnonymizedDate();
        $this->assertNotNull($anonymizedDate);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $anonymizedDate->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $anonymizedDate->getTimestamp());
    }

    public function testAnonymizeWorklogsIgnoresAlreadyAnonymized(): void
    {
        $alreadyAnonymizedDate = new \DateTime('-2 years');
        $alreadyAnonymizedId = $this->persistWorklog('already', new \DateTime(self::OLD_ENOUGH), $alreadyAnonymizedDate);
        $notYetAnonymizedId = $this->persistWorklog('not-yet', new \DateTime(self::OLD_ENOUGH));

        $affectedRows = $this->repository->anonymizeWorklogs(new \DateTime(self::CUTOFF));

        $this->assertSame(1, $affectedRows);

        $this->entityManager->clear();

        // The anonymizedDate IS NULL guard leaves the description and the original date alone,
        // so a second run cannot renumber a worklog that was already anonymized.
        $alreadyAnonymized = $this->findWorklog($alreadyAnonymizedId);
        $this->assertSame('Description of worklog already', $alreadyAnonymized->getDescription());
        $storedDate = $alreadyAnonymized->getAnonymizedDate();
        $this->assertNotNull($storedDate);
        $this->assertSame($alreadyAnonymizedDate->getTimestamp(), $storedDate->getTimestamp());

        $notYetAnonymized = $this->findWorklog($notYetAnonymizedId);
        $this->assertSame('worklog '.$notYetAnonymizedId, $notYetAnonymized->getDescription());
        $this->assertNotNull($notYetAnonymized->getAnonymizedDate());
    }

    /**
     * Persists a data provider → project → issue → worklog chain and returns the worklog id.
     *
     * Each call gets its own data provider, so the unique (data_provider_id, worklog_id) pair
     * cannot collide with a fixture row or another call.
     */
    private function persistWorklog(string $suffix, \DateTimeInterface $started, ?\DateTimeInterface $anonymizedDate = null): int
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName('anonymize-provider-'.$suffix);
        $dataProvider->setUrl('https://test.example.com');
        $dataProvider->setClass('TestClass');
        $this->entityManager->persist($dataProvider);

        $project = new Project();
        $project->setName('anonymize-project-'.$suffix);
        $project->setProjectTrackerId('anonymize-'.$suffix);
        $project->setProjectTrackerKey('anonymize-'.$suffix);
        $project->setProjectTrackerProjectUrl('https://test.example.com/project/'.$suffix);
        $project->setDataProvider($dataProvider);
        $this->entityManager->persist($project);

        $issue = new Issue();
        $issue->setName('anonymize-issue-'.$suffix);
        $issue->setProjectTrackerId('anonymize-issue-'.$suffix);
        $issue->setProjectTrackerKey('anonymize-issue-'.$suffix);
        $issue->setLinkToIssue('https://test.example.com/issue/'.$suffix);
        $issue->setProject($project);
        $issue->setDataProvider($dataProvider);
        $this->entityManager->persist($issue);

        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setDescription('Description of worklog '.$suffix);
        $worklog->setWorker('admin@test.local');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted($started);
        $worklog->setAnonymizedDate($anonymizedDate);
        $worklog->setProject($project);
        $worklog->setIssue($issue);
        $worklog->setProjectTrackerIssueId('anonymize-issue-'.$suffix);
        $worklog->setDataProvider($dataProvider);
        $this->entityManager->persist($worklog);

        $this->entityManager->flush();

        $this->createdDataProviderIds[] = $this->idOf($dataProvider->getId());
        $this->createdProjectIds[] = $this->idOf($project->getId());
        $this->createdIssueIds[] = $this->idOf($issue->getId());

        return $this->createdWorklogIds[] = $this->idOf($worklog->getId());
    }

    private function findWorklog(int $id): Worklog
    {
        $worklog = $this->repository->find($id);
        $this->assertInstanceOf(Worklog::class, $worklog);

        return $worklog;
    }

    private function idOf(?int $id): int
    {
        $this->assertNotNull($id);

        return $id;
    }

    /**
     * Removes rows a test wrote. Nothing rolls back between tests in this suite, and
     * anonymizeWorklogs() is a bulk update over the whole table, so a worklog left behind
     * changes the affected-row count the next test asserts.
     *
     * @param class-string $entityClass
     * @param list<int>    $ids
     */
    private function removeCreated(string $entityClass, array $ids): void
    {
        if ([] === $ids) {
            return;
        }

        $this->entityManager->createQuery(sprintf('DELETE FROM %s e WHERE e.id IN (:ids)', $entityClass))
            ->setParameter('ids', $ids)
            ->execute();
    }
}
