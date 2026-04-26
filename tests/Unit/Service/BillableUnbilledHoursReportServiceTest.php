<?php

namespace App\Tests\Unit\Service;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Model\Reports\BillableUnbilledHoursReportData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\BillableUnbilledHoursReportService;
use App\Service\DateTimeHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BillableUnbilledHoursReportServiceTest extends TestCase
{
    private WorklogRepository $worklogRepository;
    private DateTimeHelper $dateTimeHelper;
    private WorkerRepository $workerRepository;
    private TranslatorInterface $translator;
    private BillableUnbilledHoursReportService $service;

    protected function setUp(): void
    {
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->dateTimeHelper = $this->createMock(DateTimeHelper::class);
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->service = new BillableUnbilledHoursReportService(
            $this->worklogRepository,
            $this->dateTimeHelper,
            $this->workerRepository,
            $this->translator,
        );
    }

    public function testFullYearUsesYearDateRange(): void
    {
        $this->dateTimeHelper->expects($this->once())
            ->method('getFirstAndLastDateOfYear')
            ->with(2024)
            ->willReturn([
                'dateFrom' => new \DateTime('2024-01-01'),
                'dateTo' => new \DateTime('2024-12-31'),
            ]);

        $this->dateTimeHelper->expects($this->never())
            ->method('getFirstAndLastDateOfQuarter');

        $this->worklogRepository->method('findBillableWorklogsByWorkerAndDateRange')
            ->willReturn([]);

        $result = $this->service->getBillableUnbilledHoursReport(2024);

        $this->assertInstanceOf(BillableUnbilledHoursReportData::class, $result);
    }

    public function testQuarterUsesQuarterDateRange(): void
    {
        $this->dateTimeHelper->expects($this->once())
            ->method('getFirstAndLastDateOfQuarter')
            ->with(2024, 2)
            ->willReturn([
                'dateFrom' => new \DateTime('2024-04-01'),
                'dateTo' => new \DateTime('2024-06-30'),
            ]);

        $this->dateTimeHelper->expects($this->never())
            ->method('getFirstAndLastDateOfYear');

        $this->worklogRepository->method('findBillableWorklogsByWorkerAndDateRange')
            ->willReturn([]);

        $result = $this->service->getBillableUnbilledHoursReport(2024, 2);

        $this->assertInstanceOf(BillableUnbilledHoursReportData::class, $result);
    }

    public function testAggregatesPerProject(): void
    {
        $this->dateTimeHelper->method('getFirstAndLastDateOfYear')
            ->willReturn([
                'dateFrom' => new \DateTime('2024-01-01'),
                'dateTo' => new \DateTime('2024-12-31'),
            ]);

        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Project A');

        $issue = $this->createMock(Issue::class);
        $issue->method('getName')->willReturn('Issue 1');
        $issue->method('getProjectTrackerId')->willReturn('ISS-1');
        $issue->method('getLinkToIssue')->willReturn('http://test/1');

        $worklog1 = $this->createMock(Worklog::class);
        $worklog1->method('getProject')->willReturn($project);
        $worklog1->method('getIssue')->willReturn($issue);
        $worklog1->method('getTimeSpentSeconds')->willReturn(3600); // 1 hour
        $worklog1->method('getWorker')->willReturn('worker@test');
        $worklog1->method('getDescription')->willReturn('Work 1');

        $worklog2 = $this->createMock(Worklog::class);
        $worklog2->method('getProject')->willReturn($project);
        $worklog2->method('getIssue')->willReturn($issue);
        $worklog2->method('getTimeSpentSeconds')->willReturn(7200); // 2 hours
        $worklog2->method('getWorker')->willReturn('worker@test');
        $worklog2->method('getDescription')->willReturn('Work 2');

        $this->workerRepository->method('findOneBy')->willReturn(null);

        $this->worklogRepository->method('findBillableWorklogsByWorkerAndDateRange')
            ->willReturn([$worklog1, $worklog2]);

        $result = $this->service->getBillableUnbilledHoursReport(2024);

        // 1 + 2 = 3 hours total
        $this->assertEqualsWithDelta(3.0, $result->totalHoursForAllProjects, 0.001);
        $this->assertArrayHasKey('Project A', $result->projectTotals);
        $this->assertEqualsWithDelta(3.0, $result->projectTotals['Project A'], 0.001);
    }

    public function testEmptyWorklogsReturnsZeroTotals(): void
    {
        $this->dateTimeHelper->method('getFirstAndLastDateOfYear')
            ->willReturn([
                'dateFrom' => new \DateTime('2024-01-01'),
                'dateTo' => new \DateTime('2024-12-31'),
            ]);

        $this->worklogRepository->method('findBillableWorklogsByWorkerAndDateRange')
            ->willReturn([]);

        $result = $this->service->getBillableUnbilledHoursReport(2024);

        $this->assertEqualsWithDelta(0, $result->totalHoursForAllProjects, 0.001);
    }
}
