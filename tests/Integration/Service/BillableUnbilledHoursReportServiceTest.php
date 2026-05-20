<?php

namespace App\Tests\Integration\Service;

use App\Service\BillableUnbilledHoursReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BillableUnbilledHoursReportServiceTest extends KernelTestCase
{
    public function testGetBillableUnbilledHoursReportAggregatesBillableUnbilledWorklogs(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var BillableUnbilledHoursReportService $service */
        $service = $container->get(BillableUnbilledHoursReportService::class);

        $year = (int) (new \DateTime())->format('Y');

        $report = $service->getBillableUnbilledHoursReport($year);

        $this->assertGreaterThan(0, $report->totalHoursForAllProjects, 'Fixtures contain billable unbilled worklogs for the current year.');
        $this->assertCount(1, $report->projectData, 'Report wraps projectData as a single-element collection of project arrays.');
        $this->assertNotEmpty($report->projectTotals);

        // projectTotals should sum to the global total.
        $sum = array_sum($report->projectTotals);
        $this->assertEqualsWithDelta($report->totalHoursForAllProjects, $sum, 0.001);

        // Project keys should be sorted alphabetically.
        $projectArray = $report->projectData->first();
        $this->assertIsArray($projectArray);
        $names = array_keys($projectArray);
        $sorted = $names;
        usort($sorted, fn ($a, $b) => mb_strtolower((string) $a) <=> mb_strtolower((string) $b));
        $this->assertSame($sorted, $names, 'Projects must be sorted alphabetically (case-insensitive).');
    }

    public function testGetBillableUnbilledHoursReportRestrictsToQuarter(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var BillableUnbilledHoursReportService $service */
        $service = $container->get(BillableUnbilledHoursReportService::class);

        $year = (int) (new \DateTime())->format('Y');

        $fullYear = $service->getBillableUnbilledHoursReport($year);
        $q1 = $service->getBillableUnbilledHoursReport($year, 1);

        // A single quarter cannot exceed the full year's totals.
        $this->assertLessThanOrEqual($fullYear->totalHoursForAllProjects, $q1->totalHoursForAllProjects);
    }
}
