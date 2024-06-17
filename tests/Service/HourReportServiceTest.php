<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\Version;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use App\Service\HourReportService;
use PHPUnit\Framework\TestCase;

class HourReportServiceTest extends TestCase
{
    private HourReportService $hourReportService;
    private VersionRepository $versionRepository;

    public function setUp(): void
    {
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->versionRepository = $this->createMock(VersionRepository::class);
        $this->hourReportService = new HourReportService($this->issueRepository, $this->worklogRepository, $this->projectRepository, $this->versionRepository);
    }

    public function testGetFromDate()
    {
        $fromDate = $this->hourReportService->getFromDate();

        $expectedFromDate = (new \DateTime())->modify('first day of this month')->format('Y-m-d');

        $this->assertSame($expectedFromDate, $fromDate);
    }

    public function testGetToDate()
    {
        $toDate = $this->hourReportService->getToDate();

        $expectedToDate = (new \DateTime())->format('Y-m-d');

        $this->assertSame($expectedToDate, $toDate);
    }

    public function testGetProjects()
    {
        $expectedProjects = [
            'Project1' => 1,
            'Project2' => 2,
            'Project3' => 3,
        ];

        $mockedProjects = [];
        foreach ($expectedProjects as $name => $id) {
            $projectStub = $this->createMock(Project::class);
            $projectStub
                ->method('getName')
                ->willReturn($name);
            $projectStub
                ->method('getId')
                ->willReturn($id);
            $mockedProjects[] = $projectStub;
        }

        $this->projectRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($mockedProjects);

        $actualProjects = $this->hourReportService->getProjects();

        $this->assertEquals($expectedProjects, $actualProjects);
    }

    public function testGetMilestonesWithoutAllOption()
    {
        $this->versionRepository
            ->method('findBy')
            ->with(['project' => 1])
            ->willReturn($this->createVersionList());

        $objects = $this->hourReportService->getMilestones('1');

        $this->assertEquals([
            'Version1' => 1,
            'Version2' => 2,
        ], $objects);
    }

    public function testGetMilestonesWithAllOption()
    {
        $this->versionRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['project' => 1])
            ->willReturn($this->createVersionList());

        $objects = $this->hourReportService->getMilestones('1', true);

        $this->assertEquals([
            'All milestones' => 0,
            'Version1' => 1,
            'Version2' => 2,
        ], $objects);
    }

    private function createVersionList(): array
    {
        $versions = [];

        foreach (['Version1' => 1, 'Version2' => 2] as $name => $id) {
            $versionMock = $this->createMock(Version::class);
            $versionMock
                ->method('getName')
                ->willReturn($name);
            $versionMock
                ->method('getId')
                ->willReturn($id);
            $versions[] = $versionMock;
        }

        return $versions;
    }
}
