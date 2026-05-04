<?php

namespace App\Tests\Service;

use App\Entity\Worker;
use App\Entity\Worklog;
use App\Model\Reports\ReportContext;
use App\Model\Reports\WorkloadReportData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DateTimeHelper;
use App\Service\WorkerFilter;
use App\Service\WorkloadReportService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class WorkloadReportServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testGetWorkloadReport()
    {
        $workerMock1 = $this->createMock(Worker::class);
        $workerMock1->method('getUserIdentifier')->willReturn('test0@test');
        $workerMock1->method('getWorkload')->willReturn(40.0);
        $workerMock1->method('getId')->willReturn(21);

        $workerMock2 = $this->createMock(Worker::class);
        $workerMock2->method('getUserIdentifier')->willReturn('test1@test');
        $workerMock2->method('getWorkload')->willReturn(30.0);
        $workerMock2->method('getId')->willReturn(22);

        $workerMock3 = $this->createMock(Worker::class);
        $workerMock3->method('getUserIdentifier')->willReturn('test2@test');
        $workerMock3->method('getWorkload')->willReturn(20.0);
        $workerMock3->method('getId')->willReturn(23);

        $worklogMock1 = $this->createMock(Worklog::class);
        $worklogMock1->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findAllIncludedInReports')->willReturn([$workerMock1, $workerMock2, $workerMock3]);
        $workerFilter = new WorkerFilter($workerRepoMock);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock1]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn(range(1, 52));
        $dateTimeHelperMock->method('getMonthName')->willReturnCallback(function ($month) {
            return date('F', mktime(0, 0, 0, $month, 10));
        });
        $dateTimeHelperMock->method('getFirstAndLastDateOfWeek')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-07 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfMonth')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-31 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfYear')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-12-31 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getWeekdaysBetween')->willReturn(5);

        $workloadReportService = new WorkloadReportService($workerFilter, $worklogRepoMock, $dateTimeHelperMock);
        $context = new ReportContext(2024);

        $result = $workloadReportService->getWorkloadReport($context, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);

        $result = $workloadReportService->getWorkloadReport($context, PeriodTypeEnum::MONTH, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);

        $result = $workloadReportService->getWorkloadReport($context, PeriodTypeEnum::YEAR, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);
    }

    public function testHiddenWorkersAreExcluded()
    {
        $workerMock1 = $this->createMock(Worker::class);
        $workerMock1->method('getUserIdentifier')->willReturn('keep@test');
        $workerMock1->method('getEmail')->willReturn('keep@test');
        $workerMock1->method('getWorkload')->willReturn(37.0);

        $workerMock2 = $this->createMock(Worker::class);
        $workerMock2->method('getUserIdentifier')->willReturn('hidden@test');
        $workerMock2->method('getEmail')->willReturn('hidden@test');
        $workerMock2->method('getWorkload')->willReturn(37.0);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findAllIncludedInReports')->willReturn([$workerMock1, $workerMock2]);
        $workerFilter = new WorkerFilter($workerRepoMock);

        $worklogMock = $this->createMock(Worklog::class);
        $worklogMock->method('getTimeSpentSeconds')->willReturn(36000);
        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn([1]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfWeek')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-07 23:59:59'),
        ]);

        $workloadReportService = new WorkloadReportService($workerFilter, $worklogRepoMock, $dateTimeHelperMock);
        $context = new ReportContext(2024, null, ['hidden@test']);

        $result = $workloadReportService->getWorkloadReport($context, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
        $this->assertCount(1, $result->workers);
    }

    public function testExceptionIsThrownWhenWorkerIdentifierIsEmpty()
    {
        $workerMock1 = $this->createMock(Worker::class);
        $workerMock1->method('getUserIdentifier')->willReturn('test0@test');
        $workerMock1->method('getWorkload')->willReturn(40.0);
        $workerMock1->method('getId')->willReturn(21);
        $workerMock1->method('getIncludeInReports')->willReturn(true);

        $workerMock2 = $this->createMock(Worker::class);
        $workerMock2->method('getUserIdentifier')->willReturn('test1@test');
        $workerMock2->method('getWorkload')->willReturn(30.0);
        $workerMock2->method('getId')->willReturn(22);
        $workerMock2->method('getIncludeInReports')->willReturn(true);

        $workerMock3 = $this->createMock(Worker::class);
        $workerMock3->method('getUserIdentifier')->willReturn('');
        $workerMock3->method('getWorkload')->willReturn(20.0);
        $workerMock3->method('getId')->willReturn(23);
        $workerMock3->method('getIncludeInReports')->willReturn(true);

        $worklogMock1 = $this->createMock(Worklog::class);
        $worklogMock1->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findAllIncludedInReports')->willReturn([$workerMock1, $workerMock2, $workerMock3]);
        $workerFilter = new WorkerFilter($workerRepoMock);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock1]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn(range(1, 52));
        $dateTimeHelperMock->method('getMonthName')->willReturnCallback(function ($month) {
            return date('F', mktime(0, 0, 0, $month, 10));
        });
        $dateTimeHelperMock->method('getFirstAndLastDateOfWeek')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-07 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfMonth')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-31 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfYear')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-12-31 23:59:59'),
        ]);

        $workloadReportService = new WorkloadReportService($workerFilter, $worklogRepoMock, $dateTimeHelperMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Worker identifier cannot be empty');

        $workloadReportService->getWorkloadReport(new ReportContext(2024), PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
    }

    public function testExceptionIsThrownWhenWorkerWorkloadIsUnset()
    {
        $workerMock1 = $this->createMock(Worker::class);
        $workerMock1->method('getUserIdentifier')->willReturn('test0@test');
        $workerMock1->method('getWorkload')->willReturn(40.0);
        $workerMock1->method('getId')->willReturn(21);
        $workerMock1->method('getIncludeInReports')->willReturn(true);

        $workerMock2 = $this->createMock(Worker::class);
        $workerMock2->method('getUserIdentifier')->willReturn('test1@test');
        $workerMock2->method('getWorkload')->willReturn(30.0);
        $workerMock2->method('getId')->willReturn(22);
        $workerMock2->method('getIncludeInReports')->willReturn(true);

        $workerMock3 = $this->createMock(Worker::class);
        $workerMock3->method('getUserIdentifier')->willReturn('test2@test');
        $workerMock3->method('getWorkload')->willReturn(null);
        $workerMock3->method('getId')->willReturn(23);
        $workerMock3->method('getIncludeInReports')->willReturn(true);

        $worklogMock1 = $this->createMock(Worklog::class);
        $worklogMock1->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findAllIncludedInReports')->willReturn([$workerMock1, $workerMock2, $workerMock3]);
        $workerFilter = new WorkerFilter($workerRepoMock);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock1]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn(range(1, 52));
        $dateTimeHelperMock->method('getMonthName')->willReturnCallback(function ($month) {
            return date('F', mktime(0, 0, 0, $month, 10));
        });
        $dateTimeHelperMock->method('getFirstAndLastDateOfWeek')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-07 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfMonth')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-01-31 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getFirstAndLastDateOfYear')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-12-31 23:59:59'),
        ]);

        $workloadReportService = new WorkloadReportService($workerFilter, $worklogRepoMock, $dateTimeHelperMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Workload of worker: test2@test cannot be null when generating workload report.');

        $workloadReportService->getWorkloadReport(new ReportContext(2024), PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
    }
}
