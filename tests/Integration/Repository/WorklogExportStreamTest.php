<?php

namespace App\Tests\Integration\Repository;

use App\Entity\DataProvider;
use App\Entity\Epic;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\IssueStatusEnum;
use App\Model\Invoices\WorklogFilterData;
use App\Repository\WorklogRepository;
use App\Service\LeantimeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises WorklogRepository::streamFilteredForExport, the chunked walk behind the CSV export.
 *
 * The seed deliberately gives several worklogs the *same* started timestamp: the walk is keyset
 * paginated on started, so without the id tie-break a chunk boundary landing inside a tied group
 * would either skip rows or emit them twice. Every assertion here is run at a chunk size small
 * enough that boundaries fall inside such a group.
 */
class WorklogExportStreamTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorklogRepository $repository;

    private Project $project;
    private Project $otherProject;

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
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        parent::tearDown();
    }

    public function testEveryMatchingWorklogIsEmittedExactlyOnce(): void
    {
        $descriptions = $this->descriptions($this->projectFilter(), 2);

        $this->assertSame(
            ['newest', 'oldest', 'tied-a', 'tied-b', 'tied-c', 'tied-d', 'tied-e'],
            $this->sorted($descriptions)
        );
    }

    public function testChunkBoundariesInsideATiedGroupNeitherSkipNorDuplicate(): void
    {
        // 5 of the 7 seeded worklogs share one started value, so these chunk sizes all put a
        // boundary inside that group.
        $baseline = $this->descriptions($this->projectFilter(), 1);
        sort($baseline);

        foreach ([2, 3, 4, 7, 100, WorklogRepository::EXPORT_CHUNK_SIZE] as $chunkSize) {
            $rows = $this->descriptions($this->projectFilter(), $chunkSize);

            $this->assertSame(
                $baseline,
                $this->sorted($rows),
                sprintf('Chunk size %d changed the exported set.', $chunkSize)
            );
            $this->assertCount(
                \count($rows),
                array_unique($rows),
                sprintf('Chunk size %d emitted a duplicate row.', $chunkSize)
            );
        }
    }

    public function testRowsAreOrderedByStartedDescending(): void
    {
        $rows = $this->rows($this->projectFilter(), 2);

        $previous = null;
        foreach ($rows as $row) {
            $started = $row['started'];
            $this->assertInstanceOf(\DateTimeInterface::class, $started);

            if (null !== $previous) {
                $this->assertLessThanOrEqual(
                    $previous->getTimestamp(),
                    $started->getTimestamp(),
                    'The export must be ordered newest first.'
                );
            }

            $previous = $started;
        }

        $this->assertSame('newest', $rows[0]['description']);
        $this->assertSame('oldest', $rows[\count($rows) - 1]['description']);
    }

    public function testEpicsAndVersionsAreJoinedAndDeduplicated(): void
    {
        $row = $this->rowFor('newest');

        // The issue carries two epics and two versions. Joining both ManyToManys in one query
        // multiplies rows inside the group, so without DISTINCT each title would repeat.
        $this->assertSame('Export epic one, Export epic two', $row['epics']);
        $this->assertSame('EXP-1, EXP-2', $row['versions']);
    }

    public function testScalarColumnsCarryTheValuesTheListPageShows(): void
    {
        $row = $this->rowFor('newest');

        $this->assertSame('alice@test.local', $row['worker']);
        $this->assertSame('Worklog export project', $row['projectName']);
        $this->assertSame('Worklog export provider', $row['dataProviderName']);
        $this->assertSame('EXP-tagged', $row['issueName']);
        // The seed takes the id from the issue's tracker id, which carries a uniqid suffix.
        $this->assertStringStartsWith('EXP-tagged-', (string) $row['issueId']);
        $this->assertSame(5400, $row['timeSpentSeconds']);
        $this->assertTrue((bool) $row['isBilled']);
        $this->assertSame('Worklog export invoice', $row['invoiceName']);
    }

    public function testWorklogsWithoutAnInvoiceEntryStillExport(): void
    {
        $row = $this->rowFor('oldest');

        $this->assertNull($row['invoiceName']);
        $this->assertNull($row['epics'], 'An issue with no epics concatenates to null.');
    }

    public function testTheFilterIsHonoured(): void
    {
        $filter = $this->projectFilter();
        $filter->isBilled = true;

        $this->assertSame(['newest'], $this->descriptions($filter, 2));
    }

    public function testTheFilterExcludesOtherProjects(): void
    {
        $descriptions = $this->descriptions($this->projectFilter(), 2);

        $this->assertNotContains('other-project', $descriptions);
    }

    public function testAnEmptyResultTerminatesTheWalk(): void
    {
        $filter = $this->projectFilter();
        $filter->search = 'no-worklog-matches-this-'.uniqid();

        $this->assertSame([], $this->descriptions($filter, 2));
    }

    /**
     * The export must cover every page of the list, not just the first.
     */
    public function testTheExportSpansMorePagesThanTheListShows(): void
    {
        $filter = $this->projectFilter();

        $streamed = \count($this->descriptions($filter, 2));

        $this->assertSame(7, $streamed);
        $this->assertSame(
            $streamed,
            $this->repository->getFilteredPagination($filter, 1)->getTotalItemCount(),
            'The streamed row count must match the paginated total.'
        );
    }

    private function projectFilter(): WorklogFilterData
    {
        $filter = new WorklogFilterData();
        $filter->project = $this->project;

        return $filter;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(WorklogFilterData $filter, int $chunkSize): array
    {
        return iterator_to_array($this->repository->streamFilteredForExport($filter, $chunkSize), false);
    }

    /**
     * @return array<int, string>
     */
    private function descriptions(WorklogFilterData $filter, int $chunkSize): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['description'],
            $this->rows($filter, $chunkSize)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFor(string $description): array
    {
        foreach ($this->rows($this->projectFilter(), 3) as $row) {
            if ($description === $row['description']) {
                return $row;
            }
        }

        self::fail(sprintf('No exported row for "%s".', $description));
    }

    /**
     * @param array<int, string> $values
     *
     * @return array<int, string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    private function seed(): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName('Worklog export provider');
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://localhost/');
        $dataProvider->setSecret('Not so secret');
        $dataProvider->setEnabled(true);
        $this->entityManager->persist($dataProvider);

        $this->project = $this->makeProject('Worklog export project', 'EXP');
        $this->otherProject = $this->makeProject('Worklog export other project', 'OTH');

        $epicOne = $this->makeEpic('Export epic one');
        $epicTwo = $this->makeEpic('Export epic two');

        $taggedIssue = $this->makeIssue($this->project, 'EXP-tagged');
        $taggedIssue->addEpic($epicOne);
        $taggedIssue->addEpic($epicTwo);
        $taggedIssue->addVersion($this->makeVersion($this->project, 'EXP-1'));
        $taggedIssue->addVersion($this->makeVersion($this->project, 'EXP-2'));

        $plainIssue = $this->makeIssue($this->project, 'EXP-plain');
        $otherIssue = $this->makeIssue($this->otherProject, 'OTH-plain');

        $invoice = new Invoice();
        $invoice->setName('Worklog export invoice');
        $invoice->setProject($this->project);
        $invoice->setRecorded(false);
        $this->entityManager->persist($invoice);

        $entry = new InvoiceEntry();
        $entry->setInvoice($invoice);
        $entry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $entry->setIndex(0);
        $entry->setPrice(100.0);
        $entry->setAmount(1.0);
        $entry->setTotalPrice(100.0);
        $this->entityManager->persist($entry);

        // Five worklogs on one timestamp: the tie-break the keyset walk depends on.
        foreach (['tied-a', 'tied-b', 'tied-c', 'tied-d', 'tied-e'] as $description) {
            $this->makeWorklog($description, $this->project, $plainIssue, $dataProvider, '2026-04-10 09:00:00', false, null);
        }

        $this->makeWorklog('newest', $this->project, $taggedIssue, $dataProvider, '2026-04-20 09:00:00', true, $entry);
        $this->makeWorklog('oldest', $this->project, $plainIssue, $dataProvider, '2026-04-01 09:00:00', null, null);
        $this->makeWorklog('other-project', $this->otherProject, $otherIssue, $dataProvider, '2026-04-15 09:00:00', false, null);

        $this->entityManager->flush();
    }

    private function makeProject(string $name, string $key): Project
    {
        $project = new Project();
        $project->setName($name);
        $project->setProjectTrackerId($key.'-'.uniqid());
        $project->setProjectTrackerKey($key);
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude(true);
        $this->entityManager->persist($project);

        return $project;
    }

    private function makeEpic(string $title): Epic
    {
        $epic = new Epic();
        $epic->setTitle($title);
        $this->entityManager->persist($epic);

        return $epic;
    }

    private function makeVersion(Project $project, string $name): Version
    {
        $version = new Version();
        $version->setName($name);
        $version->setProjectTrackerId(strtolower($name).'-'.uniqid());
        $version->setProject($project);
        $this->entityManager->persist($version);

        return $version;
    }

    private function makeIssue(Project $project, string $key): Issue
    {
        $issue = new Issue();
        $issue->setName($key);
        $issue->setStatus(IssueStatusEnum::NEW);
        $issue->setProject($project);
        $issue->setProjectTrackerId($key.'-'.uniqid());
        $issue->setProjectTrackerKey($key);
        $issue->setLinkToIssue('https://tracker.example/'.$key);
        $issue->setPlanHours(null);
        $issue->setHoursRemaining(null);
        $this->entityManager->persist($issue);

        return $issue;
    }

    private function makeWorklog(
        string $description,
        Project $project,
        Issue $issue,
        DataProvider $dataProvider,
        string $started,
        ?bool $isBilled,
        ?InvoiceEntry $invoiceEntry,
    ): Worklog {
        $worklog = new Worklog();
        $worklog->setDescription($description);
        $worklog->setWorker('alice@test.local');
        $worklog->setIssue($issue);
        $worklog->setProject($project);
        $worklog->setDataProvider($dataProvider);
        $worklog->setProjectTrackerIssueId((string) $issue->getProjectTrackerId());
        $worklog->setWorklogId(random_int(1_000_000, 9_999_999));
        $worklog->setTimeSpentSeconds(5400);
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
