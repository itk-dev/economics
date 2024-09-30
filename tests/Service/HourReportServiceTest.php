<?php

namespace App\Tests\Service;

use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;
use App\Service\HourReportService;
use PHPUnit\Framework\TestCase;

class HourReportServiceTest extends TestCase
{
    private HourReportService $hourReportService;

    public function setUp(): void
    {
        $this->hourReportService = new HourReportService(
            $this->createMock(IssueRepository::class),
            $this->createMock(WorklogRepository::class),
        );
    }

    public function testGetDefaultFromDate()
    {
        $fromDate = $this->hourReportService->getDefaultFromDate();

        $expectedFromDate = (new \DateTime())->modify('first day of this month');

        $format = 'Y-m-d H:i:s';

        $this->assertEquals($expectedFromDate->format($format), $fromDate->format($format));
    }
}
