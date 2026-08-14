<?php

namespace App\Tests\Integration\Controller;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Entity\WorkerGroup;

class PlanningFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_PLANNING');
    }

    public function testIndexRedirectsToTheUserView(): void
    {
        $this->client->request('GET', '/admin/planning/');

        $this->assertResponseRedirects('/admin/planning/users');
    }

    /**
     * @dataProvider planningViewProvider
     */
    public function testPlanningViewsRender(string $url): void
    {
        $this->client->request('GET', $url);

        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function planningViewProvider(): array
    {
        return [
            'users' => ['/admin/planning/users'],
            'projects' => ['/admin/planning/projects'],
            'holiday' => ['/admin/planning/holiday'],
        ];
    }

    public function testPlanningIsDeniedForOtherRoles(): void
    {
        $this->assertDeniedFor('/admin/planning/users', ['ROLE_INVOICE']);
    }

    public function testPlanningDefaultsToTheCurrentYear(): void
    {
        $crawler = $this->client->request('GET', '/admin/planning/users');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            (new \DateTime())->format('Y'),
            $this->fieldValue($crawler->filter('form[name="planning"]')->form(), 'planning[year]')
        );
    }

    public function testPlanningAcceptsAYearFromTheForm(): void
    {
        $nextYear = (new \DateTime())->modify('+1 year')->format('Y');

        $crawler = $this->client->request('GET', '/admin/planning/users');
        $form = $crawler->filter('form[name="planning"]')->form();
        $form['planning[year]'] = $nextYear;
        $crawler = $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            $nextYear,
            $this->fieldValue($crawler->filter('form[name="planning"]')->form(), 'planning[year]')
        );
    }

    public function testPlanningCanBeScopedToAWorkerGroup(): void
    {
        $groupId = $this->requireId($this->findOne(WorkerGroup::class)->getId());

        $crawler = $this->client->request('GET', '/admin/planning/users');
        $form = $crawler->filter('form[name="planning"]')->form();
        $form['planning[group]'] = (string) $groupId;
        $crawler = $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            (string) $groupId,
            $this->fieldValue($crawler->filter('form[name="planning"]')->form(), 'planning[group]')
        );
    }

    public function testProjectsListReturnsIncludedProjectsAsOptions(): void
    {
        $excluded = $this->persistProject('Excluded planning project', include: false);
        $included = $this->persistProject('Included planning project', include: true);

        $this->client->request('GET', '/admin/planning/list/projects');

        $this->assertResponseIsSuccessful();

        $options = $this->responseJson();
        $values = array_column($options, 'value');
        $labels = array_column($options, 'label');

        $this->assertContains($included, $values);
        $this->assertNotContains($excluded, $values);
        $this->assertContains('Included planning project', $labels);
    }

    public function testSyncIssuesRequiresAProjectId(): void
    {
        $this->client->request('POST', '/admin/planning/sync-issues');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testSyncIssuesRejectsAnUnknownProject(): void
    {
        $this->client->request('POST', '/admin/planning/sync-issues?id=99999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testSyncIssuesRejectsAProjectWithoutADataProvider(): void
    {
        $id = $this->persistProject('Planning project without provider');

        $this->client->request('POST', '/admin/planning/sync-issues?id='.$id);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testSyncIssuesSucceedsForNonLeantimeProviders(): void
    {
        $id = $this->persistProject(
            'Planning project with other provider',
            dataProviderClass: 'App\Service\SomeOtherService'
        );

        $this->client->request('POST', '/admin/planning/sync-issues?id='.$id);

        $this->assertResponseIsSuccessful();
        $this->assertSame('Sync done.', $this->responseContent());
    }

    private function persistProject(
        string $name,
        bool $include = true,
        ?string $dataProviderClass = null,
    ): int {
        $project = new Project();
        $project->setName($name);
        $project->setProjectTrackerId('planning-'.uniqid());
        $project->setProjectTrackerKey('PLAN');
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude($include);

        if (null !== $dataProviderClass) {
            $dataProvider = new DataProvider();
            $dataProvider->setName('Planning provider '.uniqid());
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
