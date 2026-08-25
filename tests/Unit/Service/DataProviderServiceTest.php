<?php

namespace App\Tests\Unit\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use App\Exception\NotAcceptableException;
use App\Exception\NotFoundException;
use App\Model\DataProvider\DataProviderIssueData;
use App\Model\DataProvider\DataProviderProjectData;
use App\Model\DataProvider\DataProviderVersionData;
use App\Model\DataProvider\DataProviderWorkerData;
use App\Model\DataProvider\DataProviderWorklogData;
use App\Repository\DataProviderRepository;
use App\Repository\EpicRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DataProviderService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class DataProviderServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ProjectRepository $projectRepository;
    private IssueRepository $issueRepository;
    private WorklogRepository $worklogRepository;
    private DataProviderRepository $dataProviderRepository;
    private VersionRepository $versionRepository;
    private WorkerRepository $workerRepository;
    private EpicRepository $epicRepository;
    private ContainerInterface $transportLocator;
    private LoggerInterface $logger;
    private DataProviderService $service;
    private DataProvider $dataProvider;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->dataProviderRepository = $this->createMock(DataProviderRepository::class);
        $this->versionRepository = $this->createMock(VersionRepository::class);
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->epicRepository = $this->createMock(EpicRepository::class);
        $this->transportLocator = $this->createMock(ContainerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->dataProvider = new DataProvider();
        $this->dataProvider->setName('Test Provider');
        $this->dataProvider->setClass('TestClass');
        $this->dataProvider->setEnabled(true);

        $this->dataProviderRepository->method('find')
            ->willReturn($this->dataProvider);

        $this->service = new DataProviderService(
            $this->entityManager,
            $this->projectRepository,
            $this->issueRepository,
            $this->worklogRepository,
            $this->dataProviderRepository,
            $this->versionRepository,
            $this->workerRepository,
            $this->epicRepository,
            $this->transportLocator,
            $this->logger,
            30.0,
            40.0,
            '/^Sprint \d+$/',
        );
    }

    // -- upsertProject --

    public function testUpsertProjectCreatesNewProject(): void
    {
        $this->projectRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderProjectData(
            dataProviderId: 1,
            name: 'New Project',
            projectTrackerId: 'PT-1',
            url: 'http://test',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->service->upsertProject($data);
    }

    public function testUpsertProjectUpdatesExisting(): void
    {
        $project = new Project();
        $project->setName('Old Name');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderProjectData(
            dataProviderId: 1,
            name: 'Updated Name',
            projectTrackerId: 'PT-1',
            url: 'http://test',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime('2024-02-01'),
        );

        $this->service->upsertProject($data);

        $this->assertSame('Updated Name', $project->getName());
    }

    public function testUpsertProjectSkipsWhenModifiedDateUnchanged(): void
    {
        $modifiedDate = new \DateTime('2024-01-15 12:00:00');

        $project = new Project();
        $project->setName('Existing');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');
        $project->setSourceModifiedDate($modifiedDate);

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('flush');

        $data = new DataProviderProjectData(
            dataProviderId: 1,
            name: 'Should Not Update',
            projectTrackerId: 'PT-1',
            url: 'http://test',
            fetchTime: new \DateTime(),
            sourceModifiedDate: $modifiedDate,
        );

        $this->service->upsertProject($data);

        $this->assertSame('Existing', $project->getName());
    }

    public function testUpsertProjectForcesUpdateWhenModifiedAtCheckDisabled(): void
    {
        $modifiedDate = new \DateTime('2024-01-15 12:00:00');

        $project = new Project();
        $project->setName('Existing');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');
        $project->setSourceModifiedDate($modifiedDate);

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderProjectData(
            dataProviderId: 1,
            name: 'Force Updated',
            projectTrackerId: 'PT-1',
            url: 'http://test',
            fetchTime: new \DateTime(),
            sourceModifiedDate: $modifiedDate,
            disableModifiedAtCheck: true,
        );

        $this->service->upsertProject($data);

        $this->assertSame('Force Updated', $project->getName());
    }

    // -- upsertVersion --

    public function testUpsertVersionCreatesNew(): void
    {
        $project = new Project();
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->versionRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderVersionData(
            dataProviderId: 1,
            name: 'v1.0',
            projectTrackerId: 'VER-1',
            projectTrackerProjectId: 'PT-1',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->service->upsertVersion($data);
    }

    public function testUpsertVersionThrowsWhenProjectNotFound(): void
    {
        $this->projectRepository->method('findOneBy')->willReturn(null);

        $data = new DataProviderVersionData(
            dataProviderId: 1,
            name: 'v1.0',
            projectTrackerId: 'VER-1',
            projectTrackerProjectId: 'PT-MISSING',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->expectException(NotFoundException::class);

        $this->service->upsertVersion($data);
    }

    // -- upsertIssue --

    public function testUpsertIssueCreatesNewWithEpics(): void
    {
        $project = new Project();
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->issueRepository->method('findOneBy')->willReturn(null);
        $this->epicRepository->method('findOneBy')->willReturn(null);

        // persist called for issue + 1 epic
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderIssueData(
            projectTrackerId: 'ISS-1',
            dataProviderId: 1,
            projectTrackerProjectId: 'PT-1',
            name: 'New Issue',
            epics: ['Backend'],
            plannedHours: 10.0,
            remainingHours: 5.0,
            worker: 'test@test',
            status: IssueStatusEnum::NEW,
            dueDate: null,
            resolutionDate: null,
            fetchTime: new \DateTime(),
            url: 'http://test/1',
            sourceModifiedDate: new \DateTime(),
            versionId: null,
        );

        $this->service->upsertIssue($data);
    }

    public function testUpsertIssueSkipsEmptyEpics(): void
    {
        $project = new Project();
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->issueRepository->method('findOneBy')->willReturn(null);

        // Only persist for issue, not for empty epic
        $this->entityManager->expects($this->once())->method('persist');

        $data = new DataProviderIssueData(
            projectTrackerId: 'ISS-1',
            dataProviderId: 1,
            projectTrackerProjectId: 'PT-1',
            name: 'Issue No Epic',
            epics: ['', ''],
            plannedHours: 0.0,
            remainingHours: 0.0,
            worker: null,
            status: IssueStatusEnum::NEW,
            dueDate: null,
            resolutionDate: null,
            fetchTime: new \DateTime(),
            url: 'http://test/1',
            sourceModifiedDate: new \DateTime(),
            versionId: null,
        );

        $this->service->upsertIssue($data);
    }

    // -- upsertWorklog --

    public function testUpsertWorklogCreatesNew(): void
    {
        $issue = new Issue();
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test');

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->worklogRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderWorklogData(
            projectTrackerId: 100,
            dataProviderId: 1,
            projectTrackerIssueId: 'ISS-1',
            description: 'Did work',
            startedDate: new \DateTime('2024-01-15'),
            username: 'worker@test',
            hours: 2.5,
            kind: '',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->service->upsertWorklog($data);
    }

    public function testUpsertWorklogCalculatesTimeInSeconds(): void
    {
        $issue = new Issue();
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test');

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->worklogRepository->method('findOneBy')->willReturn(null);

        $capturedWorklog = null;
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$capturedWorklog) {
            if ($entity instanceof Worklog) {
                $capturedWorklog = $entity;
            }
        });

        $data = new DataProviderWorklogData(
            projectTrackerId: 100,
            dataProviderId: 1,
            projectTrackerIssueId: 'ISS-1',
            description: 'Work',
            startedDate: new \DateTime(),
            username: 'worker@test',
            hours: 1.5,
            kind: '',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->service->upsertWorklog($data);

        $this->assertNotNull($capturedWorklog);
        // 1.5 hours * 3600 = 5400 seconds
        $this->assertSame(5400, $capturedWorklog->getTimeSpentSeconds());
    }

    /**
     * time_spent_seconds is a signed INT. A value it cannot hold has to be refused here, because
     * MySQL refusing the insert instead is not a row-level failure to anything upstream: on the sync
     * transport it escapes updateAsJob() before the next page is queued.
     */
    public function testUpsertWorklogRejectsHoursBeyondTheColumn(): void
    {
        $this->issueRepository->method('findOneBy')->willReturn($this->worklogIssue());
        $this->worklogRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(NotAcceptableException::class);

        $this->service->upsertWorklog($this->worklogData(1 + DataProviderService::MAX_TIME_SPENT_SECONDS / DataProviderService::SECONDS_IN_HOUR));
    }

    /**
     * The bound is the column's, not a guess at a plausible working day, so a value just inside it
     * must still store.
     */
    public function testUpsertWorklogAcceptsHoursAtTheColumnLimit(): void
    {
        $this->issueRepository->method('findOneBy')->willReturn($this->worklogIssue());
        $this->worklogRepository->method('findOneBy')->willReturn(null);

        $capturedWorklog = null;
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$capturedWorklog) {
            if ($entity instanceof Worklog) {
                $capturedWorklog = $entity;
            }
        });

        $this->service->upsertWorklog($this->worklogData(DataProviderService::MAX_TIME_SPENT_SECONDS / DataProviderService::SECONDS_IN_HOUR));

        $this->assertNotNull($capturedWorklog);
        $this->assertSame(DataProviderService::MAX_TIME_SPENT_SECONDS, $capturedWorklog->getTimeSpentSeconds());
    }

    /**
     * A negative hour count is a correction at the source, not a broken row, and the column is
     * signed, so only the magnitude is bounded.
     */
    public function testUpsertWorklogKeepsNegativeHours(): void
    {
        $this->issueRepository->method('findOneBy')->willReturn($this->worklogIssue());
        $this->worklogRepository->method('findOneBy')->willReturn(null);

        $capturedWorklog = null;
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$capturedWorklog) {
            if ($entity instanceof Worklog) {
                $capturedWorklog = $entity;
            }
        });

        $this->service->upsertWorklog($this->worklogData(-1.5));

        $this->assertNotNull($capturedWorklog);
        $this->assertSame(-5400, $capturedWorklog->getTimeSpentSeconds());
    }

    /**
     * An implicit float-to-int conversion truncates, and is deprecated for any hour count that is
     * not an exact multiple of 1/3600.
     */
    public function testUpsertWorklogRoundsFractionalSeconds(): void
    {
        $this->issueRepository->method('findOneBy')->willReturn($this->worklogIssue());
        $this->worklogRepository->method('findOneBy')->willReturn(null);

        $capturedWorklog = null;
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$capturedWorklog) {
            if ($entity instanceof Worklog) {
                $capturedWorklog = $entity;
            }
        });

        // 1.00025 hours is 3600.9 seconds, which truncates to 3600.
        $this->service->upsertWorklog($this->worklogData(1.00025));

        $this->assertNotNull($capturedWorklog);
        $this->assertSame(3601, $capturedWorklog->getTimeSpentSeconds());
    }

    private function worklogIssue(): Issue
    {
        $issue = new Issue();
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test');

        return $issue;
    }

    private function worklogData(float $hours): DataProviderWorklogData
    {
        return new DataProviderWorklogData(
            projectTrackerId: 100,
            dataProviderId: 1,
            projectTrackerIssueId: 'ISS-1',
            description: 'Work',
            startedDate: new \DateTime(),
            username: 'worker@test',
            hours: $hours,
            kind: '',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );
    }

    public function testUpsertWorklogThrowsWhenIssueNotFound(): void
    {
        $this->issueRepository->method('findOneBy')->willReturn(null);

        $data = new DataProviderWorklogData(
            projectTrackerId: 100,
            dataProviderId: 1,
            projectTrackerIssueId: 'ISS-MISSING',
            description: 'Work',
            startedDate: new \DateTime(),
            username: 'worker@test',
            hours: 1.0,
            kind: '',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->expectException(NotFoundException::class);

        $this->service->upsertWorklog($data);
    }

    // -- upsertWorker --

    public function testUpsertWorkerCreatesNew(): void
    {
        $this->workerRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderWorkerData(
            dataProviderId: 1,
            name: 'John Doe',
            email: 'john@test.com',
        );

        $this->service->upsertWorker($data);
    }

    public function testUpsertWorkerDoesNotOverwriteExistingName(): void
    {
        $worker = new Worker();
        $worker->setEmail('john@test.com');
        $worker->setName('Existing Name');

        $this->workerRepository->method('findOneBy')->willReturn($worker);
        $this->entityManager->expects($this->once())->method('flush');

        $data = new DataProviderWorkerData(
            dataProviderId: 1,
            name: 'New Name',
            email: 'john@test.com',
        );

        $this->service->upsertWorker($data);

        $this->assertSame('Existing Name', $worker->getName());
    }

    public function testUpsertWorkerSetsNameWhenEmpty(): void
    {
        $worker = new Worker();
        $worker->setEmail('john@test.com');

        $this->workerRepository->method('findOneBy')->willReturn($worker);

        $data = new DataProviderWorkerData(
            dataProviderId: 1,
            name: 'John Doe',
            email: 'john@test.com',
        );

        $this->service->upsertWorker($data);

        $this->assertSame('John Doe', $worker->getName());
    }

    // -- projectRemovedFromDataProvider --

    public function testProjectRemovedNotFoundLogsWarning(): void
    {
        $this->projectRepository->method('findOneBy')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');
        $this->entityManager->expects($this->never())->method('remove');

        $this->service->projectRemovedFromDataProvider(1, 'PT-MISSING', new \DateTime());
    }

    /**
     * A project mock with every blocking relation empty, except the one named.
     *
     * All of them have to be stubbed rather than just the one under test: the service asks each
     * relation whether it isEmpty(), and an unstubbed getter hands back a generated Collection
     * whose isEmpty() is false, which would block removal for the wrong reason.
     */
    private function createProjectMock(?string $blockedBy = null): Project&MockObject
    {
        $project = $this->createMock(Project::class);

        $getters = ['getInvoices', 'getIssues', 'getWorklogs', 'getVersions', 'getProjectBillings', 'getServiceAgreements'];

        foreach ($getters as $getter) {
            $project->method($getter)->willReturn($blockedBy === $getter ? new ArrayCollection(['blocker']) : new ArrayCollection());
        }

        return $project;
    }

    public function testProjectRemovedCleanProjectGetsDeleted(): void
    {
        $project = $this->createProjectMock();

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->once())->method('remove')->with($project);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->projectRemovedFromDataProvider(1, 'PT-1', new \DateTime());
    }

    public function testProjectRemovedWithInvoicesGetsSoftDeleted(): void
    {
        $deletedDate = new \DateTime();
        $project = $this->createProjectMock('getInvoices');
        $project->method('getSourceDeletedDate')->willReturn(null);

        $project->expects($this->once())->method('setSourceDeletedDate')->with($deletedDate);

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->projectRemovedFromDataProvider(1, 'PT-1', $deletedDate);
    }

    // The three relations below point back at the project with a non-nullable, non-cascading foreign
    // key, so removing it while one exists is a database error. They have to soft delete like the
    // invoice case does.

    public function testProjectRemovedWithVersionsGetsSoftDeleted(): void
    {
        $deletedDate = new \DateTime();
        $project = $this->createProjectMock('getVersions');
        $project->method('getSourceDeletedDate')->willReturn(null);

        $project->expects($this->once())->method('setSourceDeletedDate')->with($deletedDate);

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('remove');

        $this->service->projectRemovedFromDataProvider(1, 'PT-1', $deletedDate);
    }

    public function testProjectRemovedWithProjectBillingsGetsSoftDeleted(): void
    {
        $deletedDate = new \DateTime();
        $project = $this->createProjectMock('getProjectBillings');
        $project->method('getSourceDeletedDate')->willReturn(null);

        $project->expects($this->once())->method('setSourceDeletedDate')->with($deletedDate);

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('remove');

        $this->service->projectRemovedFromDataProvider(1, 'PT-1', $deletedDate);
    }

    public function testProjectRemovedWithServiceAgreementsGetsSoftDeleted(): void
    {
        $deletedDate = new \DateTime();
        $project = $this->createProjectMock('getServiceAgreements');
        $project->method('getSourceDeletedDate')->willReturn(null);

        $project->expects($this->once())->method('setSourceDeletedDate')->with($deletedDate);

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('remove');

        $this->service->projectRemovedFromDataProvider(1, 'PT-1', $deletedDate);
    }

    public function testProjectRemovedAlreadyMarkedDeletedSkips(): void
    {
        $project = $this->createProjectMock('getInvoices');
        $project->method('getSourceDeletedDate')->willReturn(new \DateTime());

        $this->projectRepository->method('findOneBy')->willReturn($project);
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->projectRemovedFromDataProvider(1, 'PT-1', new \DateTime());
    }

    // -- versionRemovedFromDataProvider --

    public function testVersionRemovedDeletesVersion(): void
    {
        $version = new Version();
        $this->versionRepository->method('findOneBy')->willReturn($version);
        $this->entityManager->expects($this->once())->method('remove')->with($version);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->versionRemovedFromDataProvider(1, 'VER-1');
    }

    public function testVersionRemovedNotFoundLogsWarning(): void
    {
        $this->versionRepository->method('findOneBy')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');
        $this->entityManager->expects($this->never())->method('remove');

        $this->service->versionRemovedFromDataProvider(1, 'VER-MISSING');
    }

    // -- issueRemovedFromDataProvider --

    public function testIssueRemovedCleanIssueGetsDeleted(): void
    {
        $issue = $this->createMock(Issue::class);
        $issue->method('getWorklogs')->willReturn(new ArrayCollection());
        $issue->method('getSourceDeletedDate')->willReturn(null);

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->entityManager->expects($this->once())->method('remove')->with($issue);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->issueRemovedFromDataProvider(1, 'ISS-1', new \DateTime());
    }

    public function testIssueRemovedWithWorklogsGetsSoftDeleted(): void
    {
        $deletedDate = new \DateTime();
        $issue = $this->createMock(Issue::class);
        $issue->method('getWorklogs')->willReturn(new ArrayCollection(['worklog']));
        $issue->method('getSourceDeletedDate')->willReturn(null);

        $issue->expects($this->once())->method('setSourceDeletedDate')->with($deletedDate);

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->issueRemovedFromDataProvider(1, 'ISS-1', $deletedDate);
    }

    // -- worklogRemovedFromDataProvider --

    public function testWorklogRemovedFreeWorklogGetsDeleted(): void
    {
        $worklog = $this->createMock(Worklog::class);
        $worklog->method('getInvoiceEntry')->willReturn(null);
        $worklog->method('getSourceDeletedDate')->willReturn(null);

        $this->worklogRepository->method('findOneBy')->willReturn($worklog);
        $this->entityManager->expects($this->once())->method('remove')->with($worklog);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->worklogRemovedFromDataProvider(1, 100, new \DateTime());
    }

    public function testWorklogRemovedOnInvoiceGetsSoftDeleted(): void
    {
        $deletedDate = new \DateTime();
        $invoiceEntry = $this->createMock(\App\Entity\InvoiceEntry::class);
        $worklog = $this->createMock(Worklog::class);
        $worklog->method('getInvoiceEntry')->willReturn($invoiceEntry);
        $worklog->method('getSourceDeletedDate')->willReturn(null);

        $worklog->expects($this->once())->method('setSourceDeletedDate')->with($deletedDate);

        $this->worklogRepository->method('findOneBy')->willReturn($worklog);
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->worklogRemovedFromDataProvider(1, 100, $deletedDate);
    }

    public function testWorklogRemovedNotFoundLogsWarning(): void
    {
        $this->worklogRepository->method('findOneBy')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');
        $this->entityManager->expects($this->never())->method('remove');

        $this->service->worklogRemovedFromDataProvider(1, 999, new \DateTime());
    }

    // -- getDataProvider not found --

    public function testUpsertProjectThrowsWhenDataProviderNotFound(): void
    {
        // Override the default mock to return null
        $dataProviderRepo = $this->createMock(DataProviderRepository::class);
        $dataProviderRepo->method('find')->willReturn(null);

        $service = new DataProviderService(
            $this->entityManager,
            $this->projectRepository,
            $this->issueRepository,
            $this->worklogRepository,
            $dataProviderRepo,
            $this->versionRepository,
            $this->workerRepository,
            $this->epicRepository,
            $this->transportLocator,
            $this->logger,
            30.0,
            40.0,
            '/^Sprint \d+$/',
        );

        $data = new DataProviderProjectData(
            dataProviderId: 999,
            name: 'Test',
            projectTrackerId: 'PT-1',
            url: 'http://test',
            fetchTime: new \DateTime(),
            sourceModifiedDate: new \DateTime(),
        );

        $this->expectException(NotFoundException::class);

        $service->upsertProject($data);
    }
}
