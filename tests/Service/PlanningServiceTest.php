<?php

namespace App\Tests\Service;

use App\Entity\Issue as IssueEntity;
use App\Entity\Project as ProjectEntity;
use App\Entity\Worker;
use App\Enum\IssueStatusEnum;
use App\Model\Planning\PlanningData;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorkerRepository;
use App\Service\DateTimeHelper;
use App\Service\PlanningService;
use PHPUnit\Framework\TestCase;

class PlanningServiceTest extends TestCase
{
    private DateTimeHelper $dateTimeHelper;
    private IssueRepository $issueRepository;
    private WorkerRepository $workerRepository;
    private ProjectRepository $projectRepository;
    private PlanningService $service;

    protected function setUp(): void
    {
        $this->dateTimeHelper = $this->createMock(DateTimeHelper::class);
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);

        $this->service = new PlanningService(
            $this->dateTimeHelper,
            $this->issueRepository,
            30.0,
            40.0,
            $this->workerRepository,
            $this->projectRepository,
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testGetPlanningDataBuildsWeeks(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));
        $this->issueRepository->method('findIssuesInDateRange')->willReturn([]);

        $result = $this->service->getPlanningData(2024, null);

        $this->assertInstanceOf(PlanningData::class, $result);
        // With holidayPlanning=false (default), weeks are grouped into support+sprint periods
        // 52 weeks / (1 support + 3 sprint) = 13 groups * 2 entries per group = 26
        $this->assertCount(26, $result->weeks);
    }

    public function testGetPlanningDataAssigneesAreSorted(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $project = new ProjectEntity();
        $this->setEntityId($project, 1);
        $project->setName('Test Project');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $issue1 = new IssueEntity();
        $this->setEntityId($issue1, 1);
        $issue1->setName('Issue by Zoe');
        $issue1->setProjectTrackerId('ISS-1');
        $issue1->setProjectTrackerKey('ISS-1');
        $issue1->setLinkToIssue('http://test/1');
        $issue1->setProject($project);
        $issue1->setWorker('zoe@test');
        $issue1->setDueDate(new \DateTime('2024-01-15'));
        $issue1->setStatus(IssueStatusEnum::IN_PROGRESS);
        $issue1->planHours = 5.0;
        $issue1->hoursRemaining = 5.0;

        $issue2 = new IssueEntity();
        $this->setEntityId($issue2, 2);
        $issue2->setName('Issue by Alice');
        $issue2->setProjectTrackerId('ISS-2');
        $issue2->setProjectTrackerKey('ISS-2');
        $issue2->setLinkToIssue('http://test/2');
        $issue2->setProject($project);
        $issue2->setWorker('alice@test');
        $issue2->setDueDate(new \DateTime('2024-01-15'));
        $issue2->setStatus(IssueStatusEnum::IN_PROGRESS);
        $issue2->planHours = 3.0;
        $issue2->hoursRemaining = 3.0;

        $workerZoe = $this->createMock(Worker::class);
        $workerZoe->method('getName')->willReturn('Zoe');
        $workerZoe->method('getWorkload')->willReturn(37.0);

        $workerAlice = $this->createMock(Worker::class);
        $workerAlice->method('getName')->willReturn('Alice');
        $workerAlice->method('getWorkload')->willReturn(37.0);

        $this->workerRepository->method('findOneBy')->willReturnCallback(function ($criteria) use ($workerZoe, $workerAlice) {
            return match ($criteria['email']) {
                'zoe@test' => $workerZoe,
                'alice@test' => $workerAlice,
                default => null,
            };
        });

        $this->issueRepository->method('findIssuesInDateRange')->willReturn([$issue1, $issue2]);

        $result = $this->service->getPlanningData(2024, null);

        $keys = $result->assignees->getKeys();
        // Alice should come before Zoe alphabetically
        $this->assertSame('alice@test', $keys[0]);
        $this->assertSame('zoe@test', $keys[1]);
    }

    public function testDoneIssuesSkippedInNonHolidayPlanning(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $project = new ProjectEntity();
        $this->setEntityId($project, 1);
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $issue = new IssueEntity();
        $this->setEntityId($issue, 1);
        $issue->setName('Done Issue');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->setProject($project);
        $issue->setWorker('test@test');
        $issue->setDueDate(new \DateTime('2024-01-15'));
        $issue->setStatus(IssueStatusEnum::DONE);
        $issue->planHours = 0.0;
        $issue->hoursRemaining = 0.0;

        $this->workerRepository->method('findOneBy')->willReturn(null);
        $this->issueRepository->method('findIssuesInDateRange')->willReturn([$issue]);

        $result = $this->service->getPlanningData(2024, null, false);

        // DONE issue should be skipped in non-holiday planning
        $this->assertTrue($result->assignees->isEmpty());
    }

    public function testDoneIssuesIncludedInHolidayPlanning(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $project = new ProjectEntity();
        $this->setEntityId($project, 1);
        $project->setName('Holiday Project');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $issue = new IssueEntity();
        $this->setEntityId($issue, 1);
        $issue->setName('Done Holiday Issue');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->setProject($project);
        $issue->setWorker('test@test');
        $issue->setDueDate(new \DateTime('2024-01-15'));
        $issue->setStatus(IssueStatusEnum::DONE);
        $issue->planHours = 8.0;
        $issue->hoursRemaining = 8.0;

        $this->workerRepository->method('findOneBy')->willReturn(null);
        $this->projectRepository->method('findBy')->willReturn([$project]);
        $this->issueRepository->method('findIssuesInDateRange')->willReturn([$issue]);

        $result = $this->service->getPlanningData(2024, null, true);

        // DONE issue should be included in holiday planning
        $this->assertFalse($result->assignees->isEmpty());
    }

    public function testUnassignedIssues(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $project = new ProjectEntity();
        $this->setEntityId($project, 1);
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $issue = new IssueEntity();
        $this->setEntityId($issue, 1);
        $issue->setName('Unassigned Issue');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->setProject($project);
        // No worker set
        $issue->setDueDate(new \DateTime('2024-01-15'));
        $issue->setStatus(IssueStatusEnum::NEW);
        $issue->planHours = 5.0;
        $issue->hoursRemaining = 5.0;

        $this->workerRepository->method('findOneBy')->willReturn(null);
        $this->issueRepository->method('findIssuesInDateRange')->willReturn([$issue]);

        $result = $this->service->getPlanningData(2024, null);

        $this->assertTrue($result->assignees->containsKey('unassigned'));
        $assignee = $result->assignees->get('unassigned');
        $this->assertSame('Unassigned', $assignee->displayName);
    }

    public function testSprintSumAggregation(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $project = new ProjectEntity();
        $this->setEntityId($project, 1);
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $issue1 = new IssueEntity();
        $this->setEntityId($issue1, 1);
        $issue1->setName('Issue 1');
        $issue1->setProjectTrackerId('ISS-1');
        $issue1->setProjectTrackerKey('ISS-1');
        $issue1->setLinkToIssue('http://test/1');
        $issue1->setProject($project);
        $issue1->setWorker('worker@test');
        $issue1->setDueDate(new \DateTime('2024-01-08')); // Week 2
        $issue1->setStatus(IssueStatusEnum::IN_PROGRESS);
        $issue1->planHours = 5.0;
        $issue1->hoursRemaining = 5.0;

        $issue2 = new IssueEntity();
        $this->setEntityId($issue2, 2);
        $issue2->setName('Issue 2');
        $issue2->setProjectTrackerId('ISS-2');
        $issue2->setProjectTrackerKey('ISS-2');
        $issue2->setLinkToIssue('http://test/2');
        $issue2->setProject($project);
        $issue2->setWorker('worker@test');
        $issue2->setDueDate(new \DateTime('2024-01-08')); // Same week 2
        $issue2->setStatus(IssueStatusEnum::IN_PROGRESS);
        $issue2->planHours = 3.0;
        $issue2->hoursRemaining = 3.0;

        $this->workerRepository->method('findOneBy')->willReturn(null);
        $this->issueRepository->method('findIssuesInDateRange')->willReturn([$issue1, $issue2]);

        $result = $this->service->getPlanningData(2024, null);

        $assignee = $result->assignees->get('worker@test');
        $this->assertNotNull($assignee);

        // Both issues are in same week, so sprint sum should be 5 + 3 = 8
        // sortIssuesByWeek uses (int) format('W'), so week 2 not "02"
        $week = (string) ((int) (new \DateTime('2024-01-08'))->format('W'));
        $sprintSum = $assignee->sprintSums->get($week);
        $this->assertNotNull($sprintSum);
        $this->assertEqualsWithDelta(8.0, $sprintSum->sumHours, 0.001);
    }

    public function testIssuesWithoutDueDateAreSkipped(): void
    {
        $this->dateTimeHelper->method('getWeeksOfYear')->willReturn(range(1, 52));

        $project = new ProjectEntity();
        $this->setEntityId($project, 1);
        $project->setName('Test');
        $project->setProjectTrackerId('PT-1');
        $project->setProjectTrackerKey('PT-1');

        $issue = new IssueEntity();
        $this->setEntityId($issue, 1);
        $issue->setName('No Due Date');
        $issue->setProjectTrackerId('ISS-1');
        $issue->setProjectTrackerKey('ISS-1');
        $issue->setLinkToIssue('http://test/1');
        $issue->setProject($project);
        $issue->setWorker('test@test');
        // No due date set
        $issue->setStatus(IssueStatusEnum::IN_PROGRESS);
        $issue->planHours = 5.0;
        $issue->hoursRemaining = 5.0;

        $this->workerRepository->method('findOneBy')->willReturn(null);
        $this->issueRepository->method('findIssuesInDateRange')->willReturn([$issue]);

        $result = $this->service->getPlanningData(2024, null);

        $this->assertTrue($result->assignees->isEmpty());
    }
}
