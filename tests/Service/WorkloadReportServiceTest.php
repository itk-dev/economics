<?php

namespace App\Tests\Service;

use App\Entity\Worker;
use App\Entity\Worklog;
use App\Model\Reports\WorkloadReportData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
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
    protected function setUp(): void
    {
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->dateTimeHelper = $this->createMock(DateTimeHelper::class);
        $this->workloadReportService = new WorkloadReportService($this->workerRepository, $this->worklogRepository, $this->dateTimeHelper);
    }

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
        $workerRepoMock->method('findAll')->willReturn([$workerMock1, $workerMock2, $workerMock3]);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock1]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn(range(1, 52));
        $dateTimeHelperMock->method('getMonthName')->willReturnCallback(function ($month) {
            return date('F', mktime(0, 0, 0, $month, 10));
        });
        $dateTimeHelperMock->method('getFirstAndLastDatesOfWeeks')->willReturn([
            1 => [
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-01-07 23:59:59'),
            ],
        ]);

        $dateTimeHelperMock->method('getFirstAndLastDatesOfMonths')->willReturn([
            1 => [
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-01-31 23:59:59'),
            ],
        ]);

        $dateTimeHelperMock->method('getFirstAndLastDateOfYear')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-12-31 23:59:59'),
        ]);
        $dateTimeHelperMock->method('getWeekdaysBetween')->willReturn(5);
        $dateTimeHelperMock->method('getWeekdaysBetween')->willReturn(23);
        $dateTimeHelperMock->method('getWeekdaysBetween')->willReturn(262);

        $workloadReportService = new WorkloadReportService($workerRepoMock, $worklogRepoMock, $dateTimeHelperMock);

        $result = $workloadReportService->getWorkloadReport(PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);

        $result = $workloadReportService->getWorkloadReport(PeriodTypeEnum::MONTH, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);

        $result = $workloadReportService->getWorkloadReport(PeriodTypeEnum::YEAR, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);
    }

    public function testExceptionIsThrownWhenWorkerIdentifierIsEmpty()
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
        $workerMock3->method('getUserIdentifier')->willReturn('');
        $workerMock3->method('getWorkload')->willReturn(20.0);
        $workerMock3->method('getId')->willReturn(23);

        $worklogMock1 = $this->createMock(Worklog::class);
        $worklogMock1->method('getTimeSpentSeconds')->willReturn(36000);

        $worklogMock2 = $this->createMock(Worklog::class);
        $worklogMock2->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findAll')->willReturn([$workerMock1, $workerMock2, $workerMock3]);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock1]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn(range(1, 52));
        $dateTimeHelperMock->method('getMonthName')->willReturnCallback(function ($month) {
            return date('F', mktime(0, 0, 0, $month, 10));
        });
        $dateTimeHelperMock->method('getFirstAndLastDatesOfWeeks')->willReturn([
            1 => [
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-01-07 23:59:59'),
            ],
        ]);

        $dateTimeHelperMock->method('getFirstAndLastDatesOfMonths')->willReturn([
            1 => [
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-01-31 23:59:59'),
            ],
        ]);

        $dateTimeHelperMock->method('getFirstAndLastDateOfYear')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
            'dateTo' => new \DateTime('2024-12-31 23:59:59'),
        ]);

        $workloadReportService = new WorkloadReportService($workerRepoMock, $worklogRepoMock, $dateTimeHelperMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Worker identifier cannot be empty');

        // Run method that triggers exception
        $workloadReportService->getWorkloadReport(PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
    }

    public function testExceptionIsThrownWhenWorkerWorkloadIsUnset()
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
        $workerMock3->method('getWorkload')->willReturn(null);
        $workerMock3->method('getId')->willReturn(23);

        $worklogMock1 = $this->createMock(Worklog::class);
        $worklogMock1->method('getTimeSpentSeconds')->willReturn(36000);

        $worklogMock2 = $this->createMock(Worklog::class);
        $worklogMock2->method('getTimeSpentSeconds')->willReturn(36000);

        $workerRepoMock = $this->createMock(WorkerRepository::class);
        $workerRepoMock->method('findAll')->willReturn([$workerMock1, $workerMock2, $workerMock3]);

        $worklogRepoMock = $this->createMock(WorklogRepository::class);
        $worklogRepoMock->method('findWorklogsByWorkerAndDateRange')->willReturn([$worklogMock1]);

        $dateTimeHelperMock = $this->createMock(DateTimeHelper::class);
        $dateTimeHelperMock->method('getWeeksOfYear')->willReturn(range(1, 52));
        $dateTimeHelperMock->method('getMonthName')->willReturnCallback(function ($month) {
            return date('F', mktime(0, 0, 0, $month, 10));
        });
        $dateTimeHelperMock->method('getFirstAndLastDatesOfWeeks')->willReturn([
            1 => [
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-01-07 23:59:59'),
            ],
        ]);

        $dateTimeHelperMock->method('getFirstAndLastDatesOfMonths')->willReturn([
            1 => [
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-01-31 23:59:59'),
            ],
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
        $workloadReportService->getWorkloadReport(PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
    }
}
