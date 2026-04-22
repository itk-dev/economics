<?php

namespace App\Tests\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\IssueStatusEnum;
use App\Model\DataProvider\DataProviderIssueData;
use App\Repository\DataProviderRepository;
use App\Repository\EpicRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DataProviderService;
use Doctrine\ORM\EntityManagerInterface;
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
    private LoggerInterface $logger;
    private DataProviderService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->dataProviderRepository = $this->createMock(DataProviderRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DataProviderService(
            $this->entityManager,
            $this->projectRepository,
            $this->issueRepository,
            $this->worklogRepository,
            $this->dataProviderRepository,
            $this->createMock(VersionRepository::class),
            $this->createMock(WorkerRepository::class),
            $this->createMock(EpicRepository::class),
            $this->createMock(ContainerInterface::class),
            $this->logger,
            25.0,
            34.5,
            '//',
        );
    }

    public function testUpsertIssueProjectChangeUpdatesWorklogs(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getId')->willReturn(1);
        $this->dataProviderRepository->method('find')->with(1)->willReturn($dataProvider);

        $projectA = $this->createMock(Project::class);
        $projectA->method('getId')->willReturn(10);
        $projectA->method('getProjectTrackerId')->willReturn('proj-A');

        $projectB = $this->createMock(Project::class);
        $projectB->method('getId')->willReturn(20);
        $projectB->method('getProjectTrackerId')->willReturn('proj-B');

        $issue = new Issue();
        $issue->setProject($projectA);
        $issue->setDataProvider($dataProvider);

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->projectRepository->method('findOneBy')->willReturn($projectB);

        $this->worklogRepository
            ->expects($this->once())
            ->method('updateProjectByIssue')
            ->with($issue, $projectB)
            ->willReturn(3);

        $this->service->upsertIssue($this->createIssueData(1, 'proj-B'));
    }

    public function testUpsertIssueNewIssueSkipsWorklogUpdate(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getId')->willReturn(1);
        $this->dataProviderRepository->method('find')->with(1)->willReturn($dataProvider);

        $projectB = $this->createMock(Project::class);
        $projectB->method('getId')->willReturn(20);

        $this->issueRepository->method('findOneBy')->willReturn(null);
        $this->projectRepository->method('findOneBy')->willReturn($projectB);

        $this->worklogRepository
            ->expects($this->never())
            ->method('updateProjectByIssue');

        $this->service->upsertIssue($this->createIssueData(1, 'proj-B'));
    }

    public function testUpsertIssueSameProjectSkipsWorklogUpdate(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getId')->willReturn(1);
        $this->dataProviderRepository->method('find')->with(1)->willReturn($dataProvider);

        $projectA = $this->createMock(Project::class);
        $projectA->method('getId')->willReturn(10);

        $issue = new Issue();
        $issue->setProject($projectA);
        $issue->setDataProvider($dataProvider);

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->projectRepository->method('findOneBy')->willReturn($projectA);

        $this->worklogRepository
            ->expects($this->never())
            ->method('updateProjectByIssue');

        $this->service->upsertIssue($this->createIssueData(1, 'proj-A'));
    }

    public function testUpsertIssueNullProjectSkipsWorklogUpdate(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getId')->willReturn(1);
        $this->dataProviderRepository->method('find')->with(1)->willReturn($dataProvider);

        $projectA = $this->createMock(Project::class);
        $projectA->method('getId')->willReturn(10);

        $issue = new Issue();
        $issue->setProject($projectA);
        $issue->setDataProvider($dataProvider);

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->projectRepository->method('findOneBy')->willReturn(null);

        $this->worklogRepository
            ->expects($this->never())
            ->method('updateProjectByIssue');

        $this->service->upsertIssue($this->createIssueData(1, 'non-existent-project'));
    }

    public function testUpsertIssueProjectChangeWithNoWorklogs(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getId')->willReturn(1);
        $this->dataProviderRepository->method('find')->with(1)->willReturn($dataProvider);

        $projectA = $this->createMock(Project::class);
        $projectA->method('getId')->willReturn(10);
        $projectA->method('getProjectTrackerId')->willReturn('proj-A');

        $projectB = $this->createMock(Project::class);
        $projectB->method('getId')->willReturn(20);
        $projectB->method('getProjectTrackerId')->willReturn('proj-B');

        $issue = new Issue();
        $issue->setProject($projectA);
        $issue->setDataProvider($dataProvider);

        $this->issueRepository->method('findOneBy')->willReturn($issue);
        $this->projectRepository->method('findOneBy')->willReturn($projectB);

        $this->worklogRepository
            ->expects($this->once())
            ->method('updateProjectByIssue')
            ->with($issue, $projectB)
            ->willReturn(0);

        $this->service->upsertIssue($this->createIssueData(1, 'proj-B'));
    }

    private function createIssueData(int $dataProviderId, string $projectTrackerProjectId): DataProviderIssueData
    {
        return new DataProviderIssueData(
            projectTrackerId: 'issue-1',
            dataProviderId: $dataProviderId,
            projectTrackerProjectId: $projectTrackerProjectId,
            name: 'Test Issue',
            epics: [],
            plannedHours: 0.0,
            remainingHours: 0.0,
            worker: null,
            status: IssueStatusEnum::NEW,
            dueDate: null,
            resolutionDate: null,
            fetchTime: new \DateTime(),
            url: 'http://example.com/issue-1',
            sourceModifiedDate: null,
            versionId: null,
            disableModifiedAtCheck: true,
        );
    }
}
