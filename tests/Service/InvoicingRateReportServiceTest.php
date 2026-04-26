<?php

namespace App\Tests\Service;

use App\Entity\Worker;
use App\Model\Reports\InvoicingRateReportData;
use App\Model\Reports\InvoicingRateReportViewModeEnum;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DateTimeHelper;
use App\Service\InvoicingRateReportService;
use PHPUnit\Framework\TestCase;

class InvoicingRateReportServiceTest extends TestCase
{
    private WorkerRepository $workerRepository;
    private WorklogRepository $worklogRepository;
    private DateTimeHelper $dateTimeHelper;
    private InvoicingRateReportService $service;

    protected function setUp(): void
    {
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->dateTimeHelper = $this->createMock(DateTimeHelper::class);

        $this->service = new InvoicingRateReportService(
            $this->workerRepository,
            $this->worklogRepository,
            $this->dateTimeHelper,
        );
    }

    public function testMonthPeriodReturns12Periods(): void
    {
        $this->workerRepository->method('findAllIncludedInReports')->willReturn([]);
        $this->dateTimeHelper->method('getMonthName')->willReturnCallback(fn ($m) => date('F', mktime(0, 0, 0, $m, 10)));

        $result = $this->service->getInvoicingRateReport(2024, PeriodTypeEnum::MONTH);

        $this->assertInstanceOf(InvoicingRateReportData::class, $result);
        $this->assertCount(12, $result->period);
    }

    public function testWeekPeriodReturnsCorrectCount(): void
    {
        $this->workerRepository->method('findAllIncludedInReports')->willReturn([]);
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $result = $this->service->getInvoicingRateReport(2024, PeriodTypeEnum::WEEK);

        $this->assertCount(52, $result->period);
    }

    public function testExceptionOnEmptyWorkerIdentifier(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('');
        $worker->method('getWorkload')->willReturn(37.0);
        $worker->method('getName')->willReturn('Test');
        $worker->method('getIncludeInReports')->willReturn(true);

        $this->workerRepository->method('findAllIncludedInReports')->willReturn([$worker]);
        $this->dateTimeHelper->method('getMonthName')->willReturn('January');
        $this->dateTimeHelper->method('getFirstAndLastDateOfMonth')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01'),
            'dateTo' => new \DateTime('2024-01-31'),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Worker identifier cannot be empty');

        $this->service->getInvoicingRateReport(2024, PeriodTypeEnum::MONTH);
    }

    public function testPercentageCalculation(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->method('getUserIdentifier')->willReturn('test@test');
        $worker->method('getWorkload')->willReturn(37.0);
        $worker->method('getName')->willReturn('Test Worker');
        $worker->method('getIncludeInReports')->willReturn(true);

        $this->workerRepository->method('findAllIncludedInReports')->willReturn([$worker]);

        // Return empty worklogs for all 3 queries
        $this->worklogRepository->method('findWorklogsByWorkerAndDateRange')->willReturn([]);
        $this->worklogRepository->method('findBillableWorklogsByWorkerAndDateRange')->willReturn([]);
        $this->worklogRepository->method('findBilledWorklogsByWorkerAndDateRange')->willReturn([]);

        $this->dateTimeHelper->method('getMonthName')->willReturnCallback(fn ($m) => date('F', mktime(0, 0, 0, $m, 10)));
        $this->dateTimeHelper->method('getFirstAndLastDateOfMonth')->willReturn([
            'dateFrom' => new \DateTime('2024-01-01'),
            'dateTo' => new \DateTime('2024-01-31'),
        ]);

        $result = $this->service->getInvoicingRateReport(2024, PeriodTypeEnum::MONTH);

        $this->assertInstanceOf(InvoicingRateReportData::class, $result);
        $this->assertCount(1, $result->workers);

        // With 0 logged hours, average should be 0
        $workerData = $result->workers->first();
        $this->assertEqualsWithDelta(0.0, $workerData->average, 0.001);
    }

    public function testNoWorkersReturnsEmptyReport(): void
    {
        $this->workerRepository->method('findAllIncludedInReports')->willReturn([]);
        $this->dateTimeHelper->method('getMonthName')->willReturn('January');

        $result = $this->service->getInvoicingRateReport(2024, PeriodTypeEnum::MONTH);

        $this->assertCount(0, $result->workers);
        $this->assertCount(12, $result->period);
    }
}
