<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Version;
use App\Entity\WorkerGroup;
use App\Enum\IssueStatusEnum;
use App\Model\Invoices\IssueFilterData;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorkerGroupRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IssueRepositoryTest extends KernelTestCase
{
    private IssueRepository $repository;
    private ProjectRepository $projectRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $repository = $container->get(IssueRepository::class);
        \assert($repository instanceof IssueRepository);
        $this->repository = $repository;
        $projectRepository = $container->get(ProjectRepository::class);
        \assert($projectRepository instanceof ProjectRepository);
        $this->projectRepository = $projectRepository;
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new IssueFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new IssueFilterData();
        $filterData->name = 'issue-0-0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $issue) {
            $this->assertStringContains('issue-0-0', (string) $issue->getName());
        }
    }

    public function testGetFilteredPaginationByProject(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $filterData = new IssueFilterData();
        $filterData->project = $project;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $issue) {
            $issueProject = $issue->getProject();
            $this->assertNotNull($issueProject);
            $this->assertEquals($project->getId(), $issueProject->getId());
        }
    }

    public function testFindEpicOptionsByProject(): void
    {
        // project-0-0 has issue-0-0 linked to 'Epic 1'
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $result = $this->repository->findEpicOptionsByProject($project);

        $this->assertArrayHasKey('Epic 1', $result);
    }

    public function testGetClosedIssuesFromInterval(): void
    {
        // Even-index projects have DONE status issues with resolutionDate=today
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $periodStart = new \DateTime('-1 day');
        $periodEnd = new \DateTime('+1 day');

        $result = $this->repository->getClosedIssuesFromInterval($project, $periodStart, $periodEnd);

        $this->assertNotEmpty($result);
        foreach ($result as $issue) {
            $this->assertEquals(IssueStatusEnum::DONE, $issue->getStatus());
        }
    }

    public function testGetClosedIssuesFromIntervalNoResults(): void
    {
        // Odd-index projects have NEW status issues
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-1']);
        $this->assertNotNull($project);
        $periodStart = new \DateTime('-1 day');
        $periodEnd = new \DateTime('+1 day');

        $result = $this->repository->getClosedIssuesFromInterval($project, $periodStart, $periodEnd);

        $this->assertEmpty($result);
    }

    public function testIssuesContainingVersion(): void
    {
        $versionRepository = self::getContainer()->get(VersionRepository::class);
        \assert($versionRepository instanceof VersionRepository);
        $version = $versionRepository->findOneBy([], ['id' => 'ASC']);
        $this->assertNotNull($version);

        $result = $this->repository->issuesContainingVersion($version);

        $this->assertNotEmpty($result);
        foreach ($result as $issue) {
            $versionIds = $issue->getVersions()->map(fn (Version $v) => $v->getId())->toArray();
            $this->assertContains($version->getId(), $versionIds);
        }
    }

    public function testIssuesContainingVersionTitle(): void
    {
        // Fixture: every project for key=0 has a version named "PB-0-0", and
        // issues with j ∈ {0,4,8} reference that version (j % 4 == 0).
        $result = $this->repository->issuesContainingVersionTitle('PB-0-0');

        $this->assertNotEmpty($result);
        foreach ($result as $issue) {
            $versionNames = $issue->getVersions()->map(fn (Version $v) => $v->getName())->toArray();
            $this->assertContains('PB-0-0', $versionNames);
        }
    }

    public function testIssuesContainingVersionTitleUnknownReturnsEmpty(): void
    {
        $result = $this->repository->issuesContainingVersionTitle('does-not-exist-version-title');

        $this->assertSame([], $result);
    }

    public function testFindIssuesInDateRange(): void
    {
        // All fixture issues have dueDate=today
        $startDate = (new \DateTime('-1 day'))->format('Y-m-d');
        $endDate = (new \DateTime('+2 days'))->format('Y-m-d');

        $result = $this->repository->findIssuesInDateRange($startDate, $endDate);

        $this->assertNotEmpty($result);
    }

    public function testFindIssuesInDateRangeWithWorkerGroup(): void
    {
        $workerGroupRepo = self::getContainer()->get(WorkerGroupRepository::class);
        \assert($workerGroupRepo instanceof WorkerGroupRepository);
        $group = $workerGroupRepo->findOneBy(['name' => 'Group Alpha']);
        $this->assertInstanceOf(WorkerGroup::class, $group);

        $startDate = (new \DateTime('-1 day'))->format('Y-m-d');
        $endDate = (new \DateTime('+2 days'))->format('Y-m-d');

        $result = $this->repository->findIssuesInDateRange($startDate, $endDate, $group);

        $workerEmails = array_map(fn ($w) => $w->getEmail(), $group->getWorkers()->toArray());
        foreach ($result as $issue) {
            $this->assertContains($issue->getWorker(), $workerEmails);
        }
    }

    public function testFindIssuesInDateRangeWithProjects(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $this->assertNotNull($project);
        $startDate = (new \DateTime('-1 day'))->format('Y-m-d');
        $endDate = (new \DateTime('+2 days'))->format('Y-m-d');

        $result = $this->repository->findIssuesInDateRange($startDate, $endDate, null, [$project]);

        $this->assertNotEmpty($result);
        foreach ($result as $issue) {
            $issueProject = $issue->getProject();
            $this->assertNotNull($issueProject);
            $this->assertEquals($project->getId(), $issueProject->getId());
        }
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "Failed asserting that '$haystack' contains '$needle'.");
    }
}
