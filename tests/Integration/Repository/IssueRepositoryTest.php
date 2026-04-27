<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Issue;
use App\Entity\Version;
use App\Entity\WorkerGroup;
use App\Enum\IssueStatusEnum;
use App\Model\Invoices\IssueFilterData;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorkerGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IssueRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private IssueRepository $repository;
    private ProjectRepository $projectRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(IssueRepository::class);
        $this->projectRepository = $container->get(ProjectRepository::class);
    }

    public function testGetFilteredPaginationNoFilter(): void
    {
        $filterData = new IssueFilterData();
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertInstanceOf(PaginationInterface::class, $result);
        $this->assertGreaterThan(0, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new IssueFilterData();
        $filterData->name = 'issue-0-0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $issue) {
            $this->assertStringContains('issue-0-0', $issue->getName());
        }
    }

    public function testGetFilteredPaginationByProject(): void
    {
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $filterData = new IssueFilterData();
        $filterData->project = $project;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $issue) {
            $this->assertEquals($project->getId(), $issue->getProject()->getId());
        }
    }

    public function testFindEpicOptionsByProject(): void
    {
        // project-0-0 has issue-0-0 linked to 'Epic 1'
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
        $result = $this->repository->findEpicOptionsByProject($project);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Epic 1', $result);
    }

    public function testGetClosedIssuesFromInterval(): void
    {
        // Even-index projects have DONE status issues with resolutionDate=today
        $project = $this->projectRepository->findOneBy(['name' => 'project-0-0']);
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
        $periodStart = new \DateTime('-1 day');
        $periodEnd = new \DateTime('+1 day');

        $result = $this->repository->getClosedIssuesFromInterval($project, $periodStart, $periodEnd);

        $this->assertEmpty($result);
    }

    public function testIssuesContainingVersion(): void
    {
        $versionRepository = self::getContainer()->get(VersionRepository::class);
        $version = $versionRepository->findOneBy([], ['id' => 'ASC']);

        $result = $this->repository->issuesContainingVersion($version);

        $this->assertNotEmpty($result);
        foreach ($result as $issue) {
            $this->assertInstanceOf(Issue::class, $issue);
            $versionIds = $issue->getVersions()->map(fn (Version $v) => $v->getId())->toArray();
            $this->assertContains($version->getId(), $versionIds);
        }
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
        $startDate = (new \DateTime('-1 day'))->format('Y-m-d');
        $endDate = (new \DateTime('+2 days'))->format('Y-m-d');

        $result = $this->repository->findIssuesInDateRange($startDate, $endDate, null, [$project]);

        $this->assertNotEmpty($result);
        foreach ($result as $issue) {
            $this->assertEquals($project->getId(), $issue->getProject()->getId());
        }
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "Failed asserting that '$haystack' contains '$needle'.");
    }
}
