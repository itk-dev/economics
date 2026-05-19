<?php

namespace App\Tests\Integration\Controller;

use App\Repository\ProjectRepository;

class HourReportFilterTest extends AbstractControllerTestCase
{
    public function testFilterSubmissionRendersReportForSelectedProject(): void
    {
        $project = static::getContainer()->get(ProjectRepository::class)->getIncluded()
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $this->assertNotNull($project, 'Expected an included project from fixtures.');

        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $crawler = $client->request('GET', '/admin/reports/hour_report/', [
            'hour_report' => [
                'project' => (string) $project->getId(),
                'fromDate' => '2026-01-01',
                'toDate' => '2026-12-31',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form#hour_report')->count(), 'Expected hour-report filter form on the page.');
    }
}
