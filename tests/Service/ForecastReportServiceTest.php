<?php

namespace App\Tests\Service;

use App\Entity\Epic;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use App\Model\Reports\ForecastReportData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\ForecastReportService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ForecastReportServiceTest extends TestCase
{
    private WorklogRepository $worklogRepository;
    private WorkerRepository $workerRepository;
    private EntityManagerInterface $entityManager;
    private ForecastReportService $service;

    protected function setUp(): void
    {
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new ForecastReportService(
            $this->worklogRepository,
            $this->workerRepository,
            $this->entityManager,
        );
    }

    public function testGetDefaultFromDate(): void
    {
        $result = $this->service->getDefaultFromDate();
        $expected = (new \DateTime())->modify('first day of last month');

        $this->assertSame($expected->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testGetDefaultToDate(): void
    {
        $result = $this->service->getDefaultToDate();
        $expected = (new \DateTime())->modify('last day of last month');

        $this->assertSame($expected->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testEmptyWorklogsReturnsEmptyReport(): void
    {
        $this->workerRepository->method('findAll')->willReturn([]);
        $this->worklogRepository->method('getWorklogsAttachedToInvoiceInDateRange')
            ->willReturn([
                'paginator' => [],
                'pages_count' => 1,
            ]);

        $result = $this->service->getForecastReport(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertInstanceOf(ForecastReportData::class, $result);
        $this->assertEqualsWithDelta(0.0, $result->totalInvoiced, 0.001);
        $this->assertEqualsWithDelta(0.0, $result->totalInvoicedAndRecorded, 0.001);
    }

    public function testAggregatesProjectHours(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Test Project');

        $issue = $this->createMock(Issue::class);
        $issue->method('getProjectTrackerKey')->willReturn('ISS-1');
        $issue->method('getLinkToIssue')->willReturn('http://test/1');
        $issue->method('getName')->willReturn('Test Issue');
        $issue->method('getEpics')->willReturn(new ArrayCollection());
        $issue->method('getVersions')->willReturn(new ArrayCollection());

        $worklog = $this->createMock(Worklog::class);
        $worklog->method('getProject')->willReturn($project);
        $worklog->method('getIssue')->willReturn($issue);
        $worklog->method('getTimeSpentSeconds')->willReturn(7200); // 2 hours
        $worklog->method('isBilled')->willReturn(false);
        $worklog->method('getId')->willReturn(1);
        $worklog->method('getWorker')->willReturn('worker@test');
        $worklog->method('getDescription')->willReturn('Work');

        $this->workerRepository->method('findAll')->willReturn([]);
        $this->worklogRepository->method('getWorklogsAttachedToInvoiceInDateRange')
            ->willReturn([
                'paginator' => [$worklog],
                'pages_count' => 1,
            ]);

        $result = $this->service->getForecastReport(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertEqualsWithDelta(2.0, $result->totalInvoiced, 0.001);
        $this->assertEqualsWithDelta(0.0, $result->totalInvoicedAndRecorded, 0.001);
        $this->assertArrayHasKey(1, $result->projects);
        $this->assertEqualsWithDelta(2.0, $result->projects[1]->invoiced, 0.001);
    }

    public function testBilledWorklogsCountAsRecorded(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Test Project');

        $issue = $this->createMock(Issue::class);
        $issue->method('getProjectTrackerKey')->willReturn('ISS-1');
        $issue->method('getLinkToIssue')->willReturn('http://test/1');
        $issue->method('getName')->willReturn('Test Issue');
        $issue->method('getEpics')->willReturn(new ArrayCollection());
        $issue->method('getVersions')->willReturn(new ArrayCollection());

        $worklog = $this->createMock(Worklog::class);
        $worklog->method('getProject')->willReturn($project);
        $worklog->method('getIssue')->willReturn($issue);
        $worklog->method('getTimeSpentSeconds')->willReturn(3600); // 1 hour
        $worklog->method('isBilled')->willReturn(true);
        $worklog->method('getId')->willReturn(1);
        $worklog->method('getWorker')->willReturn('worker@test');
        $worklog->method('getDescription')->willReturn('Work');

        $this->workerRepository->method('findAll')->willReturn([]);
        $this->worklogRepository->method('getWorklogsAttachedToInvoiceInDateRange')
            ->willReturn([
                'paginator' => [$worklog],
                'pages_count' => 1,
            ]);

        $result = $this->service->getForecastReport(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertEqualsWithDelta(1.0, $result->totalInvoiced, 0.001);
        $this->assertEqualsWithDelta(1.0, $result->totalInvoicedAndRecorded, 0.001);
    }

    public function testWorkerNameMapping(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getEmail')->willReturn('worker@test');
        $worker->method('getName')->willReturn('John Doe');

        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Test Project');

        $issue = $this->createMock(Issue::class);
        $issue->method('getProjectTrackerKey')->willReturn('ISS-1');
        $issue->method('getLinkToIssue')->willReturn('http://test/1');
        $issue->method('getName')->willReturn('Test Issue');
        $issue->method('getEpics')->willReturn(new ArrayCollection());
        $issue->method('getVersions')->willReturn(new ArrayCollection());

        $worklog = $this->createMock(Worklog::class);
        $worklog->method('getProject')->willReturn($project);
        $worklog->method('getIssue')->willReturn($issue);
        $worklog->method('getTimeSpentSeconds')->willReturn(3600);
        $worklog->method('isBilled')->willReturn(false);
        $worklog->method('getId')->willReturn(1);
        $worklog->method('getWorker')->willReturn('worker@test');
        $worklog->method('getDescription')->willReturn('Work');

        $this->workerRepository->method('findAll')->willReturn([$worker]);
        $this->worklogRepository->method('getWorklogsAttachedToInvoiceInDateRange')
            ->willReturn([
                'paginator' => [$worklog],
                'pages_count' => 1,
            ]);

        $result = $this->service->getForecastReport(
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $projectData = $result->projects[1];
        $issueData = $projectData->issues['[no tag]'];
        $versionData = $issueData->versions['[no version]'];
        $worklogData = $versionData->worklogs[1];

        $this->assertSame('John Doe', $worklogData->worker);
    }
}
