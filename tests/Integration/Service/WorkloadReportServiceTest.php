<?php

namespace App\Tests\Integration\Service;

use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Model\Reports\WorkloadReportWorker;
use App\Service\WorkloadReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorkloadReportServiceTest extends KernelTestCase
{
    public function testGetWorkloadReportProducesPeriodsForEveryIncludedWorker(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var WorkloadReportService $service */
        $service = $container->get(WorkloadReportService::class);

        $year = (int) (new \DateTime())->format('Y');

        $report = $service->getWorkloadReport(
            $year,
            PeriodTypeEnum::WEEK,
            ViewModeEnum::WORKLOAD,
        );

        // 10 fixture workers, all included in reports.
        $this->assertCount(10, $report->workers);

        // ISO weeks in a year are either 52 or 53.
        $this->assertGreaterThanOrEqual(52, $report->period->count());
        $this->assertLessThanOrEqual(53, $report->period->count());

        $this->assertGreaterThanOrEqual(0.0, $report->totalAverage);

        /** @var WorkloadReportWorker $worker */
        $worker = $report->workers->first();
        $this->assertSame($report->period->count(), $worker->loggedPercentage->count());
        $this->assertGreaterThanOrEqual(0.0, $worker->average);
    }
}
