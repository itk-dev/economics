<?php

namespace App\Tests\Unit\Service;

use App\Entity\Worker;
use App\Entity\Worklog;
use App\Model\Reports\WorkloadReportData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportPeriodWorklogsData;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DateTimeHelper;
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

        $worklogMock2 = $this->createMock(Worklog::class);
        $worklogMock2->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findBy')->willReturn([$workerMock1, $workerMock2, $workerMock3]);

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
        $dateTimeHelperMock->method('getWeekdaysBetween')->willReturn(23);
        $dateTimeHelperMock->method('getWeekdaysBetween')->willReturn(262);

        $workloadReportService = new WorkloadReportService($workerRepoMock, $worklogRepoMock, $dateTimeHelperMock);

        $result = $workloadReportService->getWorkloadReport(2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);

        $result = $workloadReportService->getWorkloadReport(2024, PeriodTypeEnum::MONTH, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);

        $result = $workloadReportService->getWorkloadReport(2024, PeriodTypeEnum::YEAR, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);
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

        $worklogMock2 = $this->createMock(Worklog::class);
        $worklogMock2->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findBy')->willReturn([$workerMock1, $workerMock2, $workerMock3]);

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

        $workloadReportService = new WorkloadReportService($workerRepoMock, $worklogRepoMock, $dateTimeHelperMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Worker identifier cannot be empty');

        // Run method that triggers exception
        $workloadReportService->getWorkloadReport(2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
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

        $worklogMock2 = $this->createMock(Worklog::class);
        $worklogMock2->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findBy')->willReturn([$workerMock1, $workerMock2, $workerMock3]);

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

        $workloadReportService = new WorkloadReportService($workerRepoMock, $worklogRepoMock, $dateTimeHelperMock);

        // Expect this specific exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Workload of worker: test2@test cannot be null when generating workload report.');

        // Run method that triggers exception
        $workloadReportService->getWorkloadReport(2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
    }

    /**
     * @throws Exception
     */
    public function testGetPeriodWorklogsSumsAndSortsTheWorklogsBehindACell()
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('test0@test');
        $worker->method('getWorkload')->willReturn(40.0);
        $worker->method('getName')->willReturn('Test Zero');

        // Returned out of order on purpose: the repository methods do not order.
        $later = $this->createMock(Worklog::class);
        $later->method('getTimeSpentSeconds')->willReturn(36000);
        $later->method('getStarted')->willReturn(new \DateTime('2024-01-05 09:00:00'));

        $earlier = $this->createMock(Worklog::class);
        $earlier->method('getTimeSpentSeconds')->willReturn(18000);
        $earlier->method('getStarted')->willReturn(new \DateTime('2024-01-02 09:00:00'));

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$later, $earlier]);

        $service = new WorkloadReportService(
            $this->createMock(WorkerRepository::class),
            $worklogRepoMock,
            $this->getDateTimeHelperMock(),
        );

        $result = $service->getPeriodWorklogs($worker, 2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD, 1);

        $this->assertInstanceOf(WorkloadReportPeriodWorklogsData::class, $result);
        $this->assertSame('Test Zero', $result->workerName);
        $this->assertSame('1', $result->readablePeriod);
        $this->assertSame(40.0, $result->expectedWorkload);
        // 36000s + 18000s = 15 hours of an expected 40.
        $this->assertSame(15.0, $result->loggedHours);
        $this->assertSame(37.5, $result->loggedPercentage);
        $this->assertSame([$earlier, $later], $result->worklogs);
    }

    /**
     * The modal must never contradict the cell it was opened from.
     *
     * @throws Exception
     */
    public function testGetPeriodWorklogsPercentageMatchesTheReportCell()
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('test0@test');
        $worker->method('getWorkload')->willReturn(40.0);

        $worklog = $this->createMock(Worklog::class);
        $worklog->method('getTimeSpentSeconds')->willReturn(36000);
        $worklog->method('getStarted')->willReturn(new \DateTime('2024-01-02 09:00:00'));

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findBy')->willReturn([$worker]);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklog]);

        $service = new WorkloadReportService($workerRepoMock, $worklogRepoMock, $this->getDateTimeHelperMock());

        $report = $service->getWorkloadReport(2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
        $cell = $report->workers->first()->loggedPercentage->get(1);

        $result = $service->getPeriodWorklogs($worker, 2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD, 1);

        $this->assertSame($cell, $result->loggedPercentage);
    }

    /**
     * @throws Exception
     */
    public function testGetPeriodWorklogsUsesTheRepositoryMethodMatchingTheViewMode()
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('test0@test');
        $worker->method('getWorkload')->willReturn(40.0);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->expects($this->never())->method('findWorklogsByWorkerAndDateRange');
        $worklogRepoMock->expects($this->once())->method('findBillableWorklogsByWorkerAndDateRange')->willReturn([]);
        $worklogRepoMock->expects($this->once())->method('findBilledWorklogsByWorkerAndDateRange')->willReturn([]);

        $service = new WorkloadReportService(
            $this->createMock(WorkerRepository::class),
            $worklogRepoMock,
            $this->getDateTimeHelperMock(),
        );

        $service->getPeriodWorklogs($worker, 2024, PeriodTypeEnum::WEEK, ViewModeEnum::BILLABLE, 1);
        $service->getPeriodWorklogs($worker, 2024, PeriodTypeEnum::WEEK, ViewModeEnum::BILLED, 1);
    }

    /**
     * @throws Exception
     */
    public function testGetPeriodWorklogsThrowsWhenWorkerWorkloadIsUnset()
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('test2@test');
        $worker->method('getWorkload')->willReturn(null);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([]);

        $service = new WorkloadReportService(
            $this->createMock(WorkerRepository::class),
            $worklogRepoMock,
            $this->getDateTimeHelperMock(),
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Workload of worker: test2@test cannot be null when generating workload report.');

        $service->getPeriodWorklogs($worker, 2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD, 1);
    }

    /**
     * @throws Exception
     */
    public function testGetPeriodWorklogsThrowsWhenWorkerIdentifierIsEmpty()
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('');
        $worker->method('getWorkload')->willReturn(40.0);

        $service = new WorkloadReportService(
            $this->createMock(WorkerRepository::class),
            $this->createMock(WorklogRepository::class),
            $this->getDateTimeHelperMock(),
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Worker identifier cannot be empty');

        $service->getPeriodWorklogs($worker, 2024, PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD, 1);
    }

    /**
     * @throws Exception
     */
    private function getDateTimeHelperMock(): DateTimeHelper
    {
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

        return $dateTimeHelperMock;
    }
}
