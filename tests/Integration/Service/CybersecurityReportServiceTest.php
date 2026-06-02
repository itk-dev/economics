<?php

namespace App\Tests\Integration\Service;

use App\Model\Reports\CybersecurityProjectData;
use App\Service\CybersecurityReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CybersecurityReportServiceTest extends KernelTestCase
{
    private CybersecurityReportService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(CybersecurityReportService::class);
        \assert($service instanceof CybersecurityReportService);
        $this->service = $service;
    }

    public function testGetDefaultFromDateIsFirstOfCurrentMonth(): void
    {
        $expected = (new \DateTime())->modify('first day of this month');
        $this->assertSame($expected->format('Y-m-d'), $this->service->getDefaultFromDate()->format('Y-m-d'));
    }

    public function testGetDefaultToDateIsLastOfCurrentMonth(): void
    {
        $expected = (new \DateTime())->modify('last day of this month');
        $this->assertSame($expected->format('Y-m-d'), $this->service->getDefaultToDate()->format('Y-m-d'));
    }

    public function testGetCybersecurityReportUnknownVersionReturnsEmpty(): void
    {
        $report = $this->service->getCybersecurityReport(null, null, 'no-such-version');

        $this->assertSame([], $report->projects);
        $this->assertSame(0.0, $report->totalSpent);
    }

    public function testGetCybersecurityReportAggregatesByProjectAndFlagsAgreement(): void
    {
        // Fixture state for version title "PB-0-0":
        //   - present in all 10 projects under dataProvider 0 (key=0)
        //   - issues with j ∈ {0,4,8} reference this version (j % 4 == 0)
        //   - only project-0-0 has a CybersecurityAgreement attached
        //   - each issue carries 100 worklogs of 15·(k+1) minutes
        //     → 900s × Σ(1..100) = 4,545,000s = 1262.5h per issue
        $report = $this->service->getCybersecurityReport(null, null, 'PB-0-0');

        $this->assertCount(10, $report->projects);
        $this->assertEqualsWithDelta(10 * 3 * 1262.5, $report->totalSpent, 0.001);

        $secure = $report->projects['project-0-0'] ?? null;
        $this->assertInstanceOf(CybersecurityProjectData::class, $secure);
        $this->assertTrue($secure->hasCybersecurityAgreement);
        $this->assertCount(3, $secure->tickets);
        $this->assertEqualsWithDelta(3 * 1262.5, $secure->totalSpent, 0.001);

        $other = $report->projects['project-0-1'] ?? null;
        $this->assertInstanceOf(CybersecurityProjectData::class, $other);
        $this->assertFalse($other->hasCybersecurityAgreement);
        $this->assertCount(3, $other->tickets);

        foreach ($secure->tickets as $ticket) {
            $this->assertEqualsWithDelta(1262.5, $ticket->totalSpent, 0.001);
            $this->assertCount(100, $ticket->worklogs);
        }
    }

    public function testGetCybersecurityReportDateRangeExcludesOutOfRangeWorklogs(): void
    {
        // Fixture worklogs all live in the current year, so a 1970 window
        // produces zero in-range time — every ticket must be skipped.
        $longAgo = new \DateTime('1970-01-01');
        $stillAgo = new \DateTime('1970-12-31');

        $report = $this->service->getCybersecurityReport($longAgo, $stillAgo, 'PB-0-0');

        $this->assertSame([], $report->projects);
        $this->assertSame(0.0, $report->totalSpent);
    }
}
