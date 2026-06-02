<?php

namespace App\Tests\Unit\Service;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;
use App\Service\CybersecurityReportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CybersecurityReportServiceTest extends TestCase
{
    private IssueRepository&MockObject $issueRepository;
    private WorklogRepository&MockObject $worklogRepository;
    private ProjectRepository&MockObject $projectRepository;
    private CybersecurityReportService $service;

    protected function setUp(): void
    {
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);

        $this->service = new CybersecurityReportService(
            $this->issueRepository,
            $this->worklogRepository,
            $this->projectRepository,
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    private function makeProject(int $id, string $name): Project
    {
        $project = new Project();
        $this->setEntityId($project, $id);
        $project->setName($name);
        $project->setProjectTrackerId("pt-$id");
        $project->setProjectTrackerKey("key-$id");

        return $project;
    }

    private function makeIssue(int $id, Project $project, string $headline = 'Headline'): Issue
    {
        $issue = new Issue();
        $this->setEntityId($issue, $id);
        $issue->setName($headline);
        $issue->setProjectTrackerId("issue-$id");
        $issue->setProjectTrackerKey("issue-$id");
        $issue->setLinkToIssue("http://example.com/$id");
        $issue->setProject($project);

        return $issue;
    }

    private function makeWorklog(int $id, int $timeSpentSeconds, ?string $description, ?string $worker): Worklog
    {
        $worklog = new Worklog();
        $this->setEntityId($worklog, $id);
        $worklog->setWorklogId($id);
        $worklog->setTimeSpentSeconds($timeSpentSeconds);
        $worklog->setDescription($description);
        $worklog->setWorker($worker ?? 'unused@test');

        return $worklog;
    }

    public function testGetDefaultFromDateIsFirstOfMonth(): void
    {
        $fromDate = $this->service->getDefaultFromDate();

        $expected = (new \DateTime())->modify('first day of this month');

        $this->assertSame($expected->format('Y-m-d'), $fromDate->format('Y-m-d'));
    }

    public function testGetDefaultToDateIsLastOfMonth(): void
    {
        $toDate = $this->service->getDefaultToDate();

        $expected = (new \DateTime())->modify('last day of this month');

        $this->assertSame($expected->format('Y-m-d'), $toDate->format('Y-m-d'));
    }

    public function testReturnsEmptyReportWhenNoIssues(): void
    {
        $this->projectRepository
            ->method('getProjectIdsWithCybersecurityAgreement')
            ->willReturn([]);

        $this->issueRepository
            ->expects($this->once())
            ->method('issuesContainingVersionTitle')
            ->with('Cybersikkerhedsaftale')
            ->willReturn([]);

        $this->worklogRepository->expects($this->never())->method('getWorklogsByIssueAndPeriod');

        $result = $this->service->getCybersecurityReport(null, null, 'Cybersikkerhedsaftale');

        $this->assertSame([], $result->projects);
        $this->assertSame(0.0, $result->totalSpent);
    }

    public function testSkipsIssuesWithZeroTimeSpent(): void
    {
        $project = $this->makeProject(1, 'Project Alpha');
        $issue = $this->makeIssue(10, $project);

        $this->projectRepository->method('getProjectIdsWithCybersecurityAgreement')->willReturn([]);
        $this->issueRepository->method('issuesContainingVersionTitle')->willReturn([$issue]);
        $this->worklogRepository->method('getWorklogsByIssueAndPeriod')->willReturn([]);

        $result = $this->service->getCybersecurityReport(null, null, 'Cybersikkerhedsaftale');

        $this->assertSame([], $result->projects);
        $this->assertSame(0.0, $result->totalSpent);
    }

    public function testAggregatesWorklogsAndFlagsCybersecurityAgreement(): void
    {
        $projectWithAgreement = $this->makeProject(1, 'Secure Project');
        $projectWithoutAgreement = $this->makeProject(2, 'Other Project');

        $issueA = $this->makeIssue(10, $projectWithAgreement, 'Security ticket');
        $issueB = $this->makeIssue(20, $projectWithoutAgreement, 'Other ticket');

        // 1h + 0.5h = 1.5h on issueA
        $wlA1 = $this->makeWorklog(101, 3600, 'fix patch', 'alice@test');
        $wlA2 = $this->makeWorklog(102, 1800, 'second patch', 'bob@test');
        // 2h on issueB
        $wlB1 = $this->makeWorklog(201, 7200, 'unrelated work', 'carol@test');

        $this->projectRepository
            ->method('getProjectIdsWithCybersecurityAgreement')
            ->willReturn([$projectWithAgreement->getId()]);

        $this->issueRepository
            ->method('issuesContainingVersionTitle')
            ->willReturn([$issueA, $issueB]);

        $this->worklogRepository
            ->method('getWorklogsByIssueAndPeriod')
            ->willReturnMap([
                [$issueA->getId(), null, null, [$wlA1, $wlA2]],
                [$issueB->getId(), null, null, [$wlB1]],
            ]);

        $result = $this->service->getCybersecurityReport(null, null, 'Cybersikkerhedsaftale');

        $this->assertEqualsWithDelta(3.5, $result->totalSpent, 0.001);
        $this->assertCount(2, $result->projects);

        $secure = $result->projects['Secure Project'];
        $this->assertTrue($secure->hasCybersecurityAgreement);
        $this->assertEqualsWithDelta(1.5, $secure->totalSpent, 0.001);
        $this->assertCount(1, $secure->tickets);
        $this->assertSame('Security ticket', $secure->tickets[0]->headline);
        $this->assertEqualsWithDelta(1.5, $secure->tickets[0]->totalSpent, 0.001);
        $this->assertCount(2, $secure->tickets[0]->worklogs);
        $this->assertSame('alice@test', $secure->tickets[0]->worklogs[0]->worker);
        $this->assertEqualsWithDelta(1.0, $secure->tickets[0]->worklogs[0]->hours, 0.001);

        $other = $result->projects['Other Project'];
        $this->assertFalse($other->hasCybersecurityAgreement);
        $this->assertEqualsWithDelta(2.0, $other->totalSpent, 0.001);
        $this->assertCount(1, $other->tickets);
    }

    public function testGroupsMultipleTicketsUnderSameProject(): void
    {
        $project = $this->makeProject(1, 'Shared');

        $issue1 = $this->makeIssue(10, $project, 'Ticket one');
        $issue2 = $this->makeIssue(11, $project, 'Ticket two');

        $wl1 = $this->makeWorklog(101, 3600, null, 'alice@test'); // 1h
        $wl2 = $this->makeWorklog(102, 1800, null, 'bob@test');   // 0.5h

        $this->projectRepository->method('getProjectIdsWithCybersecurityAgreement')->willReturn([]);
        $this->issueRepository->method('issuesContainingVersionTitle')->willReturn([$issue1, $issue2]);
        $this->worklogRepository
            ->method('getWorklogsByIssueAndPeriod')
            ->willReturnMap([
                [$issue1->getId(), null, null, [$wl1]],
                [$issue2->getId(), null, null, [$wl2]],
            ]);

        $result = $this->service->getCybersecurityReport(null, null, 'Cybersikkerhedsaftale');

        $this->assertCount(1, $result->projects);
        $shared = $result->projects['Shared'];
        $this->assertCount(2, $shared->tickets);
        $this->assertEqualsWithDelta(1.5, $shared->totalSpent, 0.001);
        $this->assertEqualsWithDelta(1.5, $result->totalSpent, 0.001);
    }
}
