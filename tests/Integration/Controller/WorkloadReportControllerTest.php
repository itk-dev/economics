<?php

namespace App\Tests\Integration\Controller;

use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;

class WorkloadReportControllerTest extends AbstractControllerTestCase
{
    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/workload_report/',
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }

    /**
     * The index smoke test never submits the form, so the table itself is never rendered.
     * Submitting it is what exercises the drill-down buttons in every cell.
     */
    public function testSubmittedReportRendersADrillDownButtonPerCell(): void
    {
        $year = (int) (new \DateTime())->format('Y');

        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $crawler = $client->request('GET', '/admin/reports/workload_report/?'.http_build_query([
            'workload_report' => [
                'year' => $year,
                'viewMode' => 'workload',
                'viewPeriodType' => 'week',
            ],
        ]));

        $this->assertResponseIsSuccessful();

        $buttons = $crawler->filter('td button[data-url]');
        $this->assertGreaterThan(0, $buttons->count());
        $this->assertStringContainsString('/admin/reports/workload_report/worklogs', (string) $buttons->first()->attr('data-url'));
        $this->assertStringContainsString('periodType=week', (string) $buttons->first()->attr('data-url'));
        $this->assertStringContainsString('year='.$year, (string) $buttons->first()->attr('data-url'));
        $this->assertCount(1, $crawler->filter('dialog'));

        // The markup being present is not enough — Stimulus has to be wired to it. Asserting the
        // attributes catches a template helper that silently renders nothing.
        $controllers = (string) $crawler->filter('#scrollContainer')->attr('data-controller');
        $this->assertStringContainsString('show-hide', $controllers);
        $this->assertStringContainsString('worklog-details', $controllers);
        $this->assertNotNull($crawler->filter('#scrollContainer')->attr('data-worklog-details-loading-text-value'));
        $this->assertStringContainsString('worklog-details#open', (string) $buttons->first()->attr('data-action'));
        $this->assertCount(1, $crawler->filter('dialog[data-worklog-details-target="dialog"]'));
        $this->assertCount(1, $crawler->filter('[data-worklog-details-target="content"]'));
    }

    public function testWorklogsSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            $this->worklogsUrl(),
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }

    /**
     * AppFixtures seeds worklogs across the current year, so January holds rows for every worker.
     * Asking for a populated cell renders the row markup rather than just the empty state.
     */
    public function testWorklogsListsTheWorklogsOfThePeriod(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $crawler = $client->request('GET', $this->worklogsUrl(periodType: 'month', period: 1));

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
        $this->assertStringContainsString('Beskrivelse af worklog-', (string) $client->getResponse()->getContent());
    }

    public function testWorklogsRendersTheEmptyStateForACellWithoutWorklogs(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        // No fixture worklogs exist outside the current year.
        $client->request('GET', $this->worklogsUrl(year: 2000));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Ingen registreringer i perioden', (string) $client->getResponse()->getContent());
    }

    /**
     * A worklog deleted in the source still counts toward the cell, so the modal has to say so.
     * No fixture worklog is source-deleted, so this marks one and puts it back afterwards —
     * there is no DAMADoctrineTestBundle, so nothing rolls back on its own.
     */
    public function testWorklogsMarksWorklogsDeletedInTheSource(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $worklogRepository = static::getContainer()->get(WorklogRepository::class);
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->assertInstanceOf(WorklogRepository::class, $worklogRepository);

        $worklog = $worklogRepository->findOneBy(['worker' => 'report@test.local']);
        $this->assertNotNull($worklog);
        $started = $worklog->getStarted();
        $this->assertNotNull($started);

        $worklog->setSourceDeletedDate(new \DateTime());
        $entityManager->flush();

        try {
            $client->request('GET', $this->worklogsUrl(
                year: (int) $started->format('Y'),
                period: (int) $started->format('n'),
            ));

            $content = (string) $client->getResponse()->getContent();

            $this->assertResponseIsSuccessful();
            $this->assertStringContainsString('Slettet i kilden', $content);
            $this->assertStringContainsString('tælles stadig med', $content);
        } finally {
            $worklog->setSourceDeletedDate(null);
            $entityManager->flush();
        }
    }

    public function testWorklogsRejectsAnUnknownPeriodType(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', $this->worklogsUrl(periodType: 'fortnight'));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWorklogsRejectsANonNumericPeriod(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', $this->worklogsUrl(period: 'notanumber'));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWorklogsRejectsAnUnknownViewMode(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', $this->worklogsUrl(viewMode: 'guessing'));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWorklogsReturnsNotFoundForAnUnknownWorker(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', $this->worklogsUrl(worker: 'nobody@test.local'));

        $this->assertResponseStatusCodeSame(404);
    }

    private function worklogsUrl(
        string $worker = 'report@test.local',
        ?int $year = null,
        string $periodType = 'month',
        int|string $period = 1,
        string $viewMode = 'workload',
    ): string {
        return '/admin/reports/workload_report/worklogs?'.http_build_query([
            'worker' => $worker,
            'year' => $year ?? (int) (new \DateTime())->format('Y'),
            'periodType' => $periodType,
            'period' => $period,
            'viewMode' => $viewMode,
        ]);
    }
}
