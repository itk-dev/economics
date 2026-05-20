<?php

namespace App\Tests\Integration\Service;

use App\Model\Reports\InvoicingRateReportViewModeEnum;
use App\Model\Reports\InvoicingRateReportWorker;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Service\InvoicingRateReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InvoicingRateReportServiceTest extends KernelTestCase
{
    public function testGetInvoicingRateReportProducesPeriodsForEveryIncludedWorker(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $service = $container->get(InvoicingRateReportService::class);
        \assert($service instanceof InvoicingRateReportService);

        $year = (int) (new \DateTime())->format('Y');

        $report = $service->getInvoicingRateReport(
            $year,
            PeriodTypeEnum::WEEK,
            InvoicingRateReportViewModeEnum::SUMMARY,
        );

        // Fixtures create 10 workers all included in reports.
        $this->assertCount(10, $report->workers);

        // ISO weeks in a year are either 52 or 53.
        $this->assertGreaterThanOrEqual(52, $report->period->count());
        $this->assertLessThanOrEqual(53, $report->period->count());

        // totalAverage is a percentage; bound it rather than asserting an exact
        // value, since earlier integration tests in the suite may flip some
        // worklogs to billed via the recorded-invoice flow.
        $this->assertGreaterThanOrEqual(0.0, $report->totalAverage);
        $this->assertLessThanOrEqual(100.0, $report->totalAverage);
        $this->assertGreaterThan(0, $report->periodAverages->count());

        /** @var InvoicingRateReportWorker $worker */
        $worker = $report->workers->first();
        $this->assertGreaterThanOrEqual(0.0, $worker->average);
        $this->assertLessThanOrEqual(100.0, $worker->average);
        $this->assertSame($report->period->count(), $worker->dataByPeriod->count());
    }
}
