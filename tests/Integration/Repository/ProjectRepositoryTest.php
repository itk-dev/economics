<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Project;
use App\Model\Invoices\ProjectFilterData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Service\LeantimeUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProjectRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ProjectRepository::class);
    }

    public function testGetIncluded(): void
    {
        $qb = $this->repository->getIncluded();

        $this->assertInstanceOf(QueryBuilder::class, $qb);

        $results = $qb->getQuery()->getResult();
        $this->assertNotEmpty($results);

        foreach ($results as $project) {
            $this->assertTrue($project->isInclude());
        }

        // Verify ordering by name ASC
        $names = array_map(fn (Project $p) => $p->getName(), $results);
        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names);
    }

    public function testGetFilteredPaginationIncluded(): void
    {
        $filterData = new ProjectFilterData();
        $filterData->include = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertInstanceOf(PaginationInterface::class, $result);
        $this->assertGreaterThanOrEqual(20, $result->getTotalItemCount());
    }

    public function testGetFilteredPaginationByBillable(): void
    {
        $filterData = new ProjectFilterData();
        $filterData->include = null;
        $filterData->isBillable = true;
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $project) {
            $this->assertTrue($project->isBillable());
        }
    }

    public function testGetFilteredPaginationByName(): void
    {
        $filterData = new ProjectFilterData();
        $filterData->include = null;
        $filterData->name = 'project-0-0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $project) {
            $this->assertStringContainsString('project-0-0', $project->getName());
        }
    }

    public function testGetFilteredPaginationByKey(): void
    {
        $filterData = new ProjectFilterData();
        $filterData->include = null;
        $filterData->key = 'project-1-0';
        $result = $this->repository->getFilteredPagination($filterData);

        $this->assertGreaterThan(0, $result->getTotalItemCount());
        foreach ($result as $project) {
            $this->assertStringContainsString('project-1-0', $project->getProjectTrackerKey());
        }
    }

    public function testGetProjectIdsWithCybersecurityAgreement(): void
    {
        // Fixtures attach a CybersecurityAgreement to the ServiceAgreement on
        // project-0-0; no other project carries one.
        $result = $this->repository->getProjectIdsWithCybersecurityAgreement();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $project = $this->repository->findOneBy(['name' => 'project-0-0']);
        $this->assertEquals([$project->getId()], $result);
    }

    public function testGetProjectTrackerIdsByDataProviders(): void
    {
        $dpRepo = self::getContainer()->get(DataProviderRepository::class);
        $dataProviders = $dpRepo->findAll();
        $this->assertNotEmpty($dataProviders);

        $result = $this->repository->getProjectTrackerIdsByDataProviders($dataProviders);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);

        // Verify results are sorted
        $sorted = $result;
        sort($sorted);
        $this->assertEquals($sorted, $result);
    }

    public function testGetApiProjects(): void
    {
        /** @var LeantimeUrlGenerator $generator */
        $generator = self::getContainer()->get(LeantimeUrlGenerator::class);
        $result = $this->repository->getApiProjects($generator);

        $this->assertNotEmpty($result);

        foreach ($result as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('githubRepos', $item);
            $this->assertArrayHasKey('leantimeUrl', $item);
            $this->assertArrayHasKey('codeowners', $item);
            $this->assertArrayHasKey('serviceAgreement', $item);
            $this->assertIsArray($item['codeowners']);
            $this->assertTrue(null === $item['serviceAgreement'] || is_array($item['serviceAgreement']));
        }

        $withDataProvider = array_filter($result, fn (array $p): bool => !empty($p['leantimeUrl']));
        $this->assertNotEmpty($withDataProvider, 'Fixture data must contain at least one project with a data provider URL set.');
        foreach ($withDataProvider as $project) {
            $this->assertStringEndsNotWith('/', $project['leantimeUrl']);
        }
    }
}
