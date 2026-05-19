<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Worker;
use App\Repository\WorkerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorkerRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorkerRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(WorkerRepository::class);
    }

    public function testFindAllIncludedInReports(): void
    {
        $results = $this->repository->findAllIncludedInReports();

        $this->assertNotEmpty($results);
        $this->assertGreaterThanOrEqual(10, \count($results));

        foreach ($results as $worker) {
            $this->assertInstanceOf(Worker::class, $worker);
        }
    }

    public function testFindAllIncludedInReportsExcludesDisabled(): void
    {
        $worker = new Worker();
        $worker->setEmail('excluded-worker@test');
        $worker->setWorkload(37);
        $worker->setIncludeInReports(false);
        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        $results = $this->repository->findAllIncludedInReports();

        $emails = array_map(fn (Worker $w) => $w->getEmail(), $results);
        $this->assertNotContains('excluded-worker@test', $emails);
    }
}
