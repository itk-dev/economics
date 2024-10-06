<?php

namespace App\Tests\Fixtures;

use App\Repository\DataProviderRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AppFixturesTest extends KernelTestCase
{
    public function testFixtures(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->assertCount(3, $container->get(DataProviderRepository::class)->findAll());
        $this->assertCount(2 * 10 + 1, $container->get(ProjectRepository::class)->findAll());
        $this->assertCount(2 * 10 * 4 + 1, $container->get(VersionRepository::class)->findAll());
        $this->assertCount(2 * 10 * 10 + 10, $container->get(IssueRepository::class)->findAll());
        $this->assertCount(2 * 10 * 10 * 10 + 50, $container->get(WorklogRepository::class)->findAll());
    }
}
