<?php

namespace App\Tests\Service;

use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Service\SprintReportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SprintReportServiceTest extends KernelTestCase
{
    public function test1(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $projectRepository = $container->get(ProjectRepository::class);
        $versionRepository = $container->get(VersionRepository::class);

        $project = $projectRepository->findOneBy(['name' => 'project-sprint-report']);
        $version = $versionRepository->findOneBy(['name' => 'sprint-report-version']);

        $service = $container->get(SprintReportService::class);
        $sprintReport = $service->getSprintReportData($project, $version);

        $this->assertEquals(93, $sprintReport->projectHours);
        $this->assertEquals(48, $sprintReport->remainingHours);
        $this->assertEquals(45, $sprintReport->spentHours);
        $this->assertEquals(162000, $sprintReport->spentSum);
        $this->assertEquals(172800, $sprintReport->originalEstimateSum);

        $this->assertGreaterThanOrEqual(3, $sprintReport->sprints->count());
    }
}
