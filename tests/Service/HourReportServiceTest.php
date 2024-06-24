<?php

namespace App\Tests\Service;

use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
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
            $this->createMock(ProjectRepository::class),
            $this->createMock(VersionRepository::class)
        );
    }

    public function testGetFromDate()
    {
        $fromDate = $this->hourReportService->getFromDate();

        $expectedFromDate = (new \DateTime())->modify('first day of this month')->format('Y-m-d');

        $this->assertSame($expectedFromDate, $fromDate);
    }
}
