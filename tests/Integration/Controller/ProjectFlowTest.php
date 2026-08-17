<?php

namespace App\Tests\Integration\Controller;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Entity\Worker;

class ProjectFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_ADMIN');
    }

    public function testIndexListsProjects(): void
    {
        $crawler = $this->client->request('GET', '/admin/project/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('project-0-0', $crawler->html());
    }

    public function testEditFormIsRendered(): void
    {
        $id = $this->persistProject('Project To Render');

        $this->client->request('GET', sprintf('/admin/project/%d/edit', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testEditUpdatesTheProjectLead(): void
    {
        $id = $this->persistProject('Project To Edit');

        $this->submitFormAt(sprintf('/admin/project/%d/edit', $id), 'project', [
            'project[projectLeadName]' => 'New Lead',
            'project[projectLeadMail]' => 'new-lead@example.com',
            'project[githubRepos]' => 'itk-dev/economics',
        ]);

        $this->assertResponseRedirects('/admin/project/');

        $this->entityManager->clear();
        $project = $this->findById(Project::class, $id);
        $this->assertSame('New Lead', $project->getProjectLeadName());
        $this->assertSame('new-lead@example.com', $project->getProjectLeadMail());
        $this->assertSame('itk-dev/economics', $project->getGithubRepos());
    }

    public function testEditAssignsCodeowners(): void
    {
        $id = $this->persistProject('Project For Codeowners');
        $workerId = $this->requireId($this->findOne(Worker::class)->getId());
        $this->entityManager->clear();

        $this->submitFormAt(sprintf('/admin/project/%d/edit', $id), 'project', [
            'project[projectLeadName]' => 'Lead',
            'project[projectLeadMail]' => 'lead@example.com',
            'project[codeowners]' => [(string) $workerId],
        ]);

        $this->assertResponseRedirects('/admin/project/');

        $this->entityManager->clear();
        $this->assertCount(1, $this->findById(Project::class, $id)->getCodeowners());
    }

    /**
     * @dataProvider toggleProvider
     */
    public function testTogglesPersistTheirValue(string $path, string $getter, bool $value): void
    {
        $id = $this->persistProject('Project For '.$path);

        $this->requestJson('POST', sprintf('/admin/project/%d/%s', $id, $path), ['value' => $value]);

        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $project = $this->findById(Project::class, $id);
        $this->assertSame($value, $project->{$getter}());
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function toggleProvider(): array
    {
        return [
            'include on' => ['include', 'isInclude', true],
            'include off' => ['include', 'isInclude', false],
            'billable on' => ['isBillable', 'isBillable', true],
            'holiday planning on' => ['holidayPlanning', 'isHolidayPlanning', true],
        ];
    }

    /**
     * @dataProvider togglePathProvider
     */
    public function testTogglesRejectAMissingValue(string $path): void
    {
        $id = $this->persistProject('Project For Missing '.$path);

        $this->requestJson('POST', sprintf('/admin/project/%d/%s', $id, $path), ['not-value' => true]);

        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function togglePathProvider(): array
    {
        return [
            'include' => ['include'],
            'is billable' => ['isBillable'],
            'holiday planning' => ['holidayPlanning'],
        ];
    }

    public function testOptionsListsIncludedProjectsOnly(): void
    {
        $excluded = $this->persistProject('Excluded Project', include: false);
        $included = $this->persistProject('Included Project', include: true);

        $this->client->request('GET', '/admin/project/options');

        $this->assertResponseIsSuccessful();
        $options = $this->responseJson();
        $ids = array_column($options, 'id');

        $this->assertContains($included, $ids);
        $this->assertNotContains($excluded, $ids);
    }

    public function testSyncReportsAMissingDataProvider(): void
    {
        $id = $this->persistProject('Project Without Data Provider');

        $this->client->request('POST', sprintf('/admin/project/%d/sync', $id));

        $this->assertResponseStatusCodeSame(500);
        $this->assertSame(
            'Project data provider not set',
            $this->responseJson()['message']
        );
    }

    public function testSyncReportsAMissingProjectTrackerId(): void
    {
        $id = $this->persistProject('Project Without Tracker Id', projectTrackerId: null);

        $this->client->request('POST', sprintf('/admin/project/%d/sync', $id));

        $this->assertResponseStatusCodeSame(500);
        $this->assertSame(
            'Project.projectTrackerId is null',
            $this->responseJson()['message']
        );
    }

    public function testSyncSucceedsForNonLeantimeDataProviders(): void
    {
        $id = $this->persistProject('Project With Other Provider', dataProviderClass: 'App\Service\SomeOtherService');

        $this->client->request('POST', sprintf('/admin/project/%d/sync', $id));

        $this->assertResponseIsSuccessful();
    }

    private function persistProject(
        string $name,
        bool $include = true,
        ?string $projectTrackerId = 'flow-project',
        ?string $dataProviderClass = null,
    ): int {
        $project = new Project();
        $project->setName($name);
        $project->setProjectTrackerId($projectTrackerId ? $projectTrackerId.'-'.uniqid() : null);
        $project->setProjectTrackerKey('FLOW');
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude($include);

        if (null !== $dataProviderClass) {
            $dataProvider = new DataProvider();
            $dataProvider->setName('Provider '.uniqid());
            $dataProvider->setClass($dataProviderClass);
            $this->entityManager->persist($dataProvider);
            $project->setDataProvider($dataProvider);
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $id = $this->requireId($project->getId());
        $this->entityManager->clear();

        return $id;
    }
}
