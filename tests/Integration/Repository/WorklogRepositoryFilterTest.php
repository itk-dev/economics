<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Epic;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\IssueStatusEnum;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises every filter branch of WorklogRepository::findByFilterData against a
 * purpose-built project, so the assertions do not depend on fixture volume.
 */
class WorklogRepositoryFilterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorklogRepository $repository;

    private Project $project;
    private InvoiceEntry $invoiceEntry;
    private InvoiceEntry $otherInvoiceEntry;
    private Version $version;
    private Epic $epic;

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

        $this->entityManager->getConnection()->beginTransaction();

        $this->seed();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testUnscopedFilterReturnsEveryWorklogOnTheProject(): void
    {
        $this->assertSame(
            ['alice-billed', 'alice-unbilled', 'bob-other-entry', 'bob-unbilled'],
            $this->find($this->unscopedFilter())
        );
    }

    public function testOnlyAvailableIsAppliedByDefault(): void
    {
        $this->assertSame(
            ['alice-billed', 'alice-unbilled', 'bob-unbilled'],
            $this->find(new InvoiceEntryWorklogsFilterData()),
            'The filter DTO defaults onlyAvailable to true.'
        );
    }

    public function testOnlyAvailableKeepsUnassignedWorklogsAndThisEntrysOwn(): void
    {
        $filter = $this->unscopedFilter();
        $filter->onlyAvailable = true;

        $this->assertSame(['alice-billed', 'alice-unbilled', 'bob-unbilled'], $this->find($filter));
    }

    public function testFilteringOnBilledKeepsOnlyBilledWorklogs(): void
    {
        $filter = $this->unscopedFilter();
        $filter->isBilled = true;

        $this->assertSame(['alice-billed'], $this->find($filter));
    }

    public function testFilteringOnUnbilledIncludesWorklogsWithNoFlagSet(): void
    {
        $filter = $this->unscopedFilter();
        $filter->isBilled = false;

        $this->assertSame(['alice-unbilled', 'bob-other-entry', 'bob-unbilled'], $this->find($filter));
    }

    public function testWorkerFilterMatchesOnASubstring(): void
    {
        $filter = $this->unscopedFilter();
        $filter->worker = 'alice';

        $this->assertSame(['alice-billed', 'alice-unbilled'], $this->find($filter));
    }

    public function testPeriodFromExcludesEarlierWorklogs(): void
    {
        $filter = $this->unscopedFilter();
        $filter->periodFrom = new \DateTime('2026-03-10');

        $this->assertSame(['bob-other-entry', 'bob-unbilled'], $this->find($filter));
    }

    public function testPeriodToIncludesTheSelectedDay(): void
    {
        $filter = $this->unscopedFilter();
        $filter->periodTo = new \DateTime('2026-03-01');

        $this->assertSame(['alice-billed'], $this->find($filter));
    }

    public function testPeriodRangeCombinesBothBounds(): void
    {
        $filter = $this->unscopedFilter();
        $filter->periodFrom = new \DateTime('2026-03-01');
        $filter->periodTo = new \DateTime('2026-03-05');

        $this->assertSame(['alice-billed', 'alice-unbilled'], $this->find($filter));
    }

    public function testVersionFilterKeepsWorklogsOnIssuesInThatVersion(): void
    {
        $filter = $this->unscopedFilter();
        $filter->version = $this->version;

        $this->assertSame(['alice-billed', 'alice-unbilled'], $this->find($filter));
    }

    public function testEpicFilterKeepsWorklogsOnIssuesInThatEpic(): void
    {
        $filter = $this->unscopedFilter();
        $filter->epics = [$this->epic->getId()];

        $this->assertSame(['alice-billed', 'alice-unbilled'], $this->find($filter));
    }

    public function testFiltersCombine(): void
    {
        $filter = new InvoiceEntryWorklogsFilterData();
        $filter->worker = 'alice';
        $filter->isBilled = false;
        $filter->version = $this->version;
        $filter->onlyAvailable = true;

        $this->assertSame(['alice-unbilled'], $this->find($filter));
    }

    private function unscopedFilter(): InvoiceEntryWorklogsFilterData
    {
        $filter = new InvoiceEntryWorklogsFilterData();
        $filter->onlyAvailable = false;

        return $filter;
    }

    /**
     * @return string[]
     */
    private function find(InvoiceEntryWorklogsFilterData $filter): array
    {
        $results = $this->repository->findByFilterData($this->project, $this->invoiceEntry, $filter);

        $descriptions = [];
        foreach ($results as $worklog) {
            $descriptions[] = (string) $worklog->getDescription();
        }
        sort($descriptions);

        return $descriptions;
    }

    private function seed(): void
    {
        $this->project = new Project();
        $this->project->setName('Worklog filter project');
        $this->project->setProjectTrackerId('worklog-filter-'.uniqid());
        $this->project->setProjectTrackerKey('WFP');
        $this->project->setProjectTrackerProjectUrl('http://localhost/');
        $this->project->setInclude(true);
        $this->entityManager->persist($this->project);

        $this->version = new Version();
        $this->version->setName('WFP-1');
        $this->version->setProjectTrackerId('wfp-version-'.uniqid());
        $this->version->setProject($this->project);
        $this->entityManager->persist($this->version);

        $this->epic = new Epic();
        $this->epic->setTitle('WFP epic');
        $this->entityManager->persist($this->epic);

        $taggedIssue = $this->makeIssue('WFP-tagged');
        $taggedIssue->addVersion($this->version);
        $taggedIssue->addEpic($this->epic);
        $plainIssue = $this->makeIssue('WFP-plain');

        $invoice = new Invoice();
        $invoice->setName('Worklog filter invoice');
        $invoice->setProject($this->project);
        $invoice->setRecorded(false);
        $this->entityManager->persist($invoice);

        $this->invoiceEntry = $this->makeEntry($invoice);
        $this->otherInvoiceEntry = $this->makeEntry($invoice);

        // started dates straddle the period bounds the tests filter on.
        $this->makeWorklog('alice-billed', 'alice@test.local', $taggedIssue, '2026-03-01', true, null);
        $this->makeWorklog('alice-unbilled', 'alice@test.local', $taggedIssue, '2026-03-05', false, $this->invoiceEntry);
        $this->makeWorklog('bob-unbilled', 'bob@test.local', $plainIssue, '2026-03-15', null, null);
        $this->makeWorklog('bob-other-entry', 'bob@test.local', $plainIssue, '2026-03-20', false, $this->otherInvoiceEntry);

        $this->entityManager->flush();
    }

    private function makeIssue(string $key): Issue
    {
        $issue = new Issue();
        $issue->setName($key);
        $issue->setStatus(IssueStatusEnum::NEW);
        $issue->setProject($this->project);
        $issue->setProjectTrackerId($key.'-'.uniqid());
        $issue->setProjectTrackerKey($key);
        $issue->setLinkToIssue('https://tracker.example/'.$key);
        $issue->setPlanHours(null);
        $issue->setHoursRemaining(null);
        $this->entityManager->persist($issue);

        return $issue;
    }

    private function makeEntry(Invoice $invoice): InvoiceEntry
    {
        $entry = new InvoiceEntry();
        $entry->setInvoice($invoice);
        $entry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $entry->setIndex(0);
        $entry->setPrice(100.0);
        $entry->setAmount(1.0);
        $entry->setTotalPrice(100.0);
        $this->entityManager->persist($entry);

        return $entry;
    }

    private function makeWorklog(
        string $description,
        string $worker,
        Issue $issue,
        string $started,
        ?bool $isBilled,
        ?InvoiceEntry $invoiceEntry,
    ): Worklog {
        $worklog = new Worklog();
        $worklog->setDescription($description);
        $worklog->setWorker($worker);
        $worklog->setIssue($issue);
        $worklog->setProject($this->project);
        $worklog->setProjectTrackerIssueId((string) $issue->getProjectTrackerId());
        $worklog->setWorklogId(random_int(1_000_000, 9_999_999));
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime($started));
        $worklog->setKind(null);

        if (null !== $isBilled) {
            $worklog->setIsBilled($isBilled);
        }

        if (null !== $invoiceEntry) {
            $worklog->setInvoiceEntry($invoiceEntry);
        }

        $this->entityManager->persist($worklog);

        return $worklog;
    }
}
