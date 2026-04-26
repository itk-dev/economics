<?php

namespace App\Tests\Unit\Service;

use App\Entity\Epic;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Model\Reports\HourReportData;
use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;
use App\Service\HourReportService;
use PHPUnit\Framework\TestCase;

class HourReportServiceTest extends TestCase
{
    private IssueRepository $issueRepository;
    private WorklogRepository $worklogRepository;
    private HourReportService $hourReportService;

    public function setUp(): void
    {
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);

        $this->hourReportService = new HourReportService(
            $this->issueRepository,
            $this->worklogRepository,
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testGetDefaultFromDate(): void
    {
        $fromDate = $this->hourReportService->getDefaultFromDate();

        $expectedFromDate = (new \DateTime())->modify('first day of this month');

        $format = 'Y-m-d';

        $this->assertEquals($expectedFromDate->format($format), $fromDate->format($format));
    }

    public function testGetDefaultToDate(): void
    {
        $toDate = $this->hourReportService->getDefaultToDate();

        $expectedToDate = (new \DateTime())->modify('last day of this month');

        $format = 'Y-m-d';

        $this->assertEquals($expectedToDate->format($format), $toDate->format($format));
    }

    public function testGetHourReportWithVersionFilter(): void
    {
        $version = $this->createMock(Version::class);
        $project = $this->createMock(Project::class);

        $this->issueRepository->expects($this->once())
            ->method('issuesContainingVersion')
            ->with($version)
            ->willReturn([]);

        $this->issueRepository->expects($this->never())
            ->method('findBy');

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
            $version,
        );

        $this->assertInstanceOf(HourReportData::class, $result);
    }

    public function testGetHourReportWithoutVersionUsesProjectFilter(): void
    {
        $project = $this->createMock(Project::class);

        $this->issueRepository->expects($this->once())
            ->method('findBy')
            ->with(['project' => $project])
            ->willReturn([]);

        $this->issueRepository->expects($this->never())
            ->method('issuesContainingVersion');

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertInstanceOf(HourReportData::class, $result);
    }

    public function testGetHourReportSkipsIssuesWithNoWorklogsInRange(): void
    {
        $project = $this->createMock(Project::class);

        $issue = new Issue();
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISSUE-1');
        $issue->setProjectTrackerKey('TEST-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->planHours = 10.0;

        // Worklog outside the date range
        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setWorker('test@test');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime('2023-06-15'));
        $worklog->setProjectTrackerIssueId('ISSUE-1');

        $this->issueRepository->method('findBy')->willReturn([$issue]);
        $this->worklogRepository->method('findBy')->willReturn([$worklog]);

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertEqualsWithDelta(0.0, $result->projectTotalSpent, 0.001);
        $this->assertTrue($result->projectTags->isEmpty());
    }

    public function testGetHourReportAggregatesWorklogsInRange(): void
    {
        $project = $this->createMock(Project::class);

        $issue = new Issue();
        $this->setEntityId($issue, 1);
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISSUE-1');
        $issue->setProjectTrackerKey('TEST-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->planHours = 10.0;

        $worklog1 = new Worklog();
        $worklog1->setWorklogId(1);
        $worklog1->setWorker('test@test');
        $worklog1->setTimeSpentSeconds(3600); // 1 hour
        $worklog1->setStarted(new \DateTime('2024-01-10'));
        $worklog1->setProjectTrackerIssueId('ISSUE-1');

        $worklog2 = new Worklog();
        $worklog2->setWorklogId(2);
        $worklog2->setWorker('test@test');
        $worklog2->setTimeSpentSeconds(7200); // 2 hours
        $worklog2->setStarted(new \DateTime('2024-01-15'));
        $worklog2->setProjectTrackerIssueId('ISSUE-1');

        $this->issueRepository->method('findBy')->willReturn([$issue]);
        $this->worklogRepository->method('findBy')->willReturn([$worklog1, $worklog2]);

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertEqualsWithDelta(3.0, $result->projectTotalSpent, 0.001);
        $this->assertEqualsWithDelta(10.0, $result->projectTotalEstimated, 0.001);
    }

    public function testGetHourReportGroupsByEpic(): void
    {
        $project = $this->createMock(Project::class);

        $epic = new Epic();
        $epic->setTitle('Backend Work');

        $issue = new Issue();
        $this->setEntityId($issue, 1);
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISSUE-1');
        $issue->setProjectTrackerKey('TEST-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->planHours = 5.0;
        $issue->addEpic($epic);

        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setWorker('test@test');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime('2024-01-10'));
        $worklog->setProjectTrackerIssueId('ISSUE-1');

        $this->issueRepository->method('findBy')->willReturn([$issue]);
        $this->worklogRepository->method('findBy')->willReturn([$worklog]);

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertTrue($result->projectTags->containsKey('Backend Work'));
        $tag = $result->projectTags->get('Backend Work');
        $this->assertSame('Backend Work', $tag->tag);
    }

    public function testGetHourReportMultipleEpicsCommaJoined(): void
    {
        $project = $this->createMock(Project::class);

        $epic1 = new Epic();
        $epic1->setTitle('Backend');

        $epic2 = new Epic();
        $epic2->setTitle('API');

        $issue = new Issue();
        $this->setEntityId($issue, 1);
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISSUE-1');
        $issue->setProjectTrackerKey('TEST-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->planHours = 5.0;
        $issue->addEpic($epic1);
        $issue->addEpic($epic2);

        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setWorker('test@test');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime('2024-01-10'));
        $worklog->setProjectTrackerIssueId('ISSUE-1');

        $this->issueRepository->method('findBy')->willReturn([$issue]);
        $this->worklogRepository->method('findBy')->willReturn([$worklog]);

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertTrue($result->projectTags->containsKey('Backend, API'));
    }

    public function testGetHourReportNoEpicUsesNoTag(): void
    {
        $project = $this->createMock(Project::class);

        $issue = new Issue();
        $this->setEntityId($issue, 1);
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISSUE-1');
        $issue->setProjectTrackerKey('TEST-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->planHours = 5.0;
        // No epic

        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setWorker('test@test');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime('2024-01-10'));
        $worklog->setProjectTrackerIssueId('ISSUE-1');

        $this->issueRepository->method('findBy')->willReturn([$issue]);
        $this->worklogRepository->method('findBy')->willReturn([$worklog]);

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        // Empty epic name gets mapped to 'noTag' in HourReportProjectTag constructor
        $this->assertTrue($result->projectTags->containsKey(''));
    }

    public function testGetHourReportMultipleIssuesSameEpicAggregated(): void
    {
        $project = $this->createMock(Project::class);

        $epic = new Epic();
        $epic->setTitle('Frontend');

        $issue1 = new Issue();
        $this->setEntityId($issue1, 1);
        $issue1->setName('Issue 1');
        $issue1->setProjectTrackerId('ISSUE-1');
        $issue1->setProjectTrackerKey('TEST-1');
        $issue1->setLinkToIssue('http://test/1');
        $issue1->planHours = 5.0;
        $issue1->addEpic($epic);

        $issue2 = new Issue();
        $this->setEntityId($issue2, 2);
        $issue2->setName('Issue 2');
        $issue2->setProjectTrackerId('ISSUE-2');
        $issue2->setProjectTrackerKey('TEST-2');
        $issue2->setLinkToIssue('http://test/2');
        $issue2->planHours = 3.0;
        $issue2->addEpic($epic);

        $worklog1 = new Worklog();
        $worklog1->setWorklogId(1);
        $worklog1->setWorker('test@test');
        $worklog1->setTimeSpentSeconds(3600); // 1h
        $worklog1->setStarted(new \DateTime('2024-01-10'));
        $worklog1->setProjectTrackerIssueId('ISSUE-1');

        $worklog2 = new Worklog();
        $worklog2->setWorklogId(2);
        $worklog2->setWorker('test@test');
        $worklog2->setTimeSpentSeconds(7200); // 2h
        $worklog2->setStarted(new \DateTime('2024-01-15'));
        $worklog2->setProjectTrackerIssueId('ISSUE-2');

        $this->issueRepository->method('findBy')->willReturn([$issue1, $issue2]);
        $this->worklogRepository->method('findBy')
            ->willReturnOnConsecutiveCalls([$worklog1], [$worklog2]);

        $result = $this->hourReportService->getHourReport(
            $project,
            new \DateTime('2024-01-01'),
            new \DateTime('2024-01-31'),
        );

        $this->assertEqualsWithDelta(3.0, $result->projectTotalSpent, 0.001);
        $this->assertEqualsWithDelta(8.0, $result->projectTotalEstimated, 0.001);

        $tag = $result->projectTags->get('Frontend');
        $this->assertEqualsWithDelta(8.0, $tag->totalEstimated, 0.001);
        $this->assertEqualsWithDelta(3.0, $tag->totalSpent, 0.001);
        $this->assertCount(2, $tag->projectTickets);
    }
}
