<?php

namespace App\Tests\Service;

use App\Entity\Worker;
use App\Model\Reports\WorkloadReportData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DateTimeHelper;
use App\Service\WorkloadReportService;
use PHPUnit\Framework\TestCase;

class WorkloadReportServiceTest extends TestCase
{
    /**
     * @covers ::getViewPeriodTypes
     */
    public function testGetViewPeriodTypes()
    {
        $dependency1 = $this->createMock(WorkerRepository::class);
        $dependency2 = $this->createMock(WorklogRepository::class);
        $dependency3 = $this->createMock(DateTimeHelper::class);

        $workloadReportService = new WorkloadReportService($dependency1, $dependency2, $dependency3);
        $result = $workloadReportService->getViewPeriodTypes();

        $expectedResult = [
            'Week' => 'week',
            'Month' => 'month',
            'Year' => 'year',
        ];

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @covers ::getWorkloadReport
     */
    public function testGetWorkloadReport()
    {
        $workerMock = $this->createMock(Worker::class);
        $workerMock->method('getUserIdentifier')->willReturn('test0@test');

        $dependency1 = $this->createMock(WorkerRepository::class);
        $dependency1->method('findAll')->willReturn($workerMock);
        $dependency1->method('find')->willReturn($workerMock);
        $dependency2 = $this->createMock(WorklogRepository::class);
        $dependency3 = $this->createMock(DateTimeHelper::class);

        // Set up WorkloadService with mocks
        $workloadReportService = new WorkloadReportService($dependency1, $dependency2, $dependency3);

        // Test for 'week' period type
        $result = $workloadReportService->getWorkloadReport(PeriodTypeEnum::WEEK, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);
        // Test for 'month' period type
        $result = $workloadReportService->getWorkloadReport(PeriodTypeEnum::MONTH, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);
        // Test for 'year' period type
        $result = $workloadReportService->getWorkloadReport(PeriodTypeEnum::YEAR, ViewModeEnum::WORKLOAD);
        $this->assertInstanceOf(WorkloadReportData::class, $result);
    }
}
