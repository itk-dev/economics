<?php

namespace App\Tests\Integration\Controller;

use App\Entity\ProjectBilling;
use App\Repository\ProjectBillingRepository;
use App\Repository\ProjectRepository;

class ProjectBillingFlowTest extends AbstractControllerTestCase
{
    public function testCreateProjectBillingPersistsAndRedirectsToEdit(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_PROJECT_BILLING']);
        $crawler = $client->request('GET', '/admin/project-billing/new');
        $this->assertResponseIsSuccessful();

        $projectRepository = static::getContainer()->get(ProjectRepository::class);
        \assert($projectRepository instanceof ProjectRepository);
        $project = $projectRepository->getIncluded()
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $this->assertNotNull($project, 'Expected an included project from fixtures.');

        $name = 'Smoke-billing-'.uniqid();
        $form = $crawler->filter('form[name="project_billing"]')->form();
        $form['project_billing[name]'] = $name;
        $form['project_billing[project]'] = (string) $project->getId();
        $form['project_billing[periodStart]'] = '2026-01-01';
        $form['project_billing[periodEnd]'] = '2026-01-31';
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/admin/project-billing/\d+/edit$#', $client->getResponse()->headers->get('Location'));

        $projectBillingRepository = static::getContainer()->get(ProjectBillingRepository::class);
        \assert($projectBillingRepository instanceof ProjectBillingRepository);
        $created = $projectBillingRepository->findOneBy(['name' => $name]);
        $this->assertInstanceOf(ProjectBilling::class, $created);
        $this->assertSame($project->getId(), $created->getProject()->getId());
    }
}
