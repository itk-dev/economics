<?php

namespace App\Tests\Integration\Service;

use App\Service\ForecastReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ForecastReportServiceTest extends KernelTestCase
{
    public function testGetForecastReportRunsAgainstFixturesAndReturnsConsistentTotals(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ForecastReportService $service */
        $service = $container->get(ForecastReportService::class);

        // Cover the entire fixture year plus a year of headroom on either side.
        $year = (int) (new \DateTime())->format('Y');
        $fromDate = new \DateTime(($year - 1).'-01-01');
        $toDate = new \DateTime(($year + 1).'-12-31');

        $report = $service->getForecastReport($fromDate, $toDate);

        $this->assertGreaterThanOrEqual(0.0, $report->totalInvoiced);
        $this->assertGreaterThanOrEqual(0.0, $report->totalInvoicedAndRecorded);

        // Recorded ⊆ invoiced, so the recorded total cannot exceed the invoiced total.
        $this->assertLessThanOrEqual($report->totalInvoiced, $report->totalInvoicedAndRecorded);
    }
}
