<?php

namespace App\Tests\Integration\Service;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\HourReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HourReportServiceTest extends KernelTestCase
{
    public function testGetHourReportAggregatesWorklogsAcrossIssuesAndEpicTag(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $container->get(ProjectRepository::class);
        /** @var HourReportService $service */
        $service = $container->get(HourReportService::class);

        // project-0-0 is the only project whose first issue is tagged with "Epic 1" in fixtures.
        $project = $projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertInstanceOf(Project::class, $project);

        $report = $service->getHourReport($project, null, null);

        // Each project has 10 issues × 100 worklogs; worklog k contributes (k+1)*15 minutes.
        // Total = 10 issues × 900s × Σ(1..100) = 10 × 900 × 5050 = 45,450,000s = 12_625h.
        $this->assertEqualsWithDelta(12625.0, $report->projectTotalSpent, 0.001);

        // Issue planHours equals issue index j (0..9) → Σ = 45h estimated per project.
        $this->assertEqualsWithDelta(45.0, $report->projectTotalEstimated, 0.001);

        // One issue (issue-0-0) has Epic 1; the other 9 have no epic → "noTag".
        // The collection is keyed by raw epic name ('' for no-epic); the displayed
        // label lives on the tag value's `tag` property.
        $this->assertCount(2, $report->projectTags);
        $tagsByLabel = [];
        foreach ($report->projectTags as $projectTag) {
            $tagsByLabel[$projectTag->tag] = $projectTag;
        }
        $this->assertArrayHasKey('Epic 1', $tagsByLabel);
        $this->assertArrayHasKey('noTag', $tagsByLabel);

        $epicTag = $tagsByLabel['Epic 1'];
        $this->assertCount(1, $epicTag->projectTickets);
        $this->assertEqualsWithDelta(1262.5, $epicTag->totalSpent, 0.001);

        $noTag = $tagsByLabel['noTag'];
        $this->assertCount(9, $noTag->projectTickets);
    }

    public function testGetHourReportDateRangeExcludesOutOfRangeWorklogs(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $container->get(ProjectRepository::class);
        /** @var HourReportService $service */
        $service = $container->get(HourReportService::class);

        $project = $projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertInstanceOf(Project::class, $project);

        // Fixture worklogs are spread across the current year only.
        $longAgo = new \DateTime('1970-01-01');
        $stillAgo = new \DateTime('1970-12-31');

        $report = $service->getHourReport($project, $longAgo, $stillAgo);

        $this->assertSame(0.0, $report->projectTotalSpent);
        $this->assertCount(0, $report->projectTags, 'Issues with zero in-range hours must be excluded.');
    }
}
