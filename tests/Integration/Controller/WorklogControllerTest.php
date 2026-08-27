<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Worklog;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

class WorklogControllerTest extends AbstractControllerTestCase
{
    private const URL = '/admin/worklog/';

    /**
     * Descriptions in AppFixtures are "Beskrivelse af worklog-{provider}-{project}-{issue}-{k}",
     * so this substring matches exactly one of the ~20.000 fixture worklogs.
     */
    private const UNIQUE_WORKLOG = 'worklog-0-0-0-0';

    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            self::URL,
            allowedRoles: ['ROLE_ADMIN'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }

    public function testIndexListsAPageOfWorklogs(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $crawler = $client->request('GET', self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertCount(25, $crawler->filter('tbody tr[data-worklog-id]'));
    }

    public function testSearchMatchesDescriptionAndIssueId(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $crawler = $this->filter($client, ['search' => self::UNIQUE_WORKLOG]);

        $rows = $crawler->filter('tbody tr[data-worklog-id]');
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Beskrivelse af '.self::UNIQUE_WORKLOG, $rows->text());
    }

    public function testFilterByDataProvider(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        /** @var DataProviderRepository $repository */
        $repository = static::getContainer()->get(DataProviderRepository::class);
        $dataProvider = $repository->findOneBy(['name' => 'Data Provider 2 - Leantime 2']);
        $this->assertNotNull($dataProvider);

        $crawler = $this->filter($client, ['dataProvider' => (string) $dataProvider->getId()]);

        $rows = $crawler->filter('tbody tr[data-worklog-id]');
        $this->assertGreaterThan(0, $rows->count());
        foreach ($rows as $row) {
            $text = (new Crawler($row))->text();
            $this->assertStringContainsString('Data Provider 2 - Leantime 2', $text);
            $this->assertStringNotContainsString('Data Provider 1 - Leantime 1', $text);
        }
    }

    public function testFilterByProject(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        /** @var ProjectRepository $repository */
        $repository = static::getContainer()->get(ProjectRepository::class);
        $project = $repository->findOneBy(['name' => 'project-0-3']);
        $this->assertNotNull($project);

        $crawler = $this->filter($client, ['project' => (string) $project->getId()]);

        $rows = $crawler->filter('tbody tr[data-worklog-id]');
        $this->assertGreaterThan(0, $rows->count());
        foreach ($rows as $row) {
            $this->assertStringContainsString('project-0-3', (new Crawler($row))->text());
        }
    }

    public function testFilterByWorker(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        // AppFixtures assigns every worklog of project-{provider}-3 to the fourth fixture worker.
        /** @var WorkerRepository $repository */
        $repository = static::getContainer()->get(WorkerRepository::class);
        $worker = $repository->findOneBy(['email' => 'project-billing@test.local']);
        $this->assertNotNull($worker);

        $crawler = $this->filter($client, ['worker' => (string) $worker->getId()]);

        $rows = $crawler->filter('tbody tr[data-worklog-id]');
        $this->assertGreaterThan(0, $rows->count());
        foreach ($rows as $row) {
            $this->assertStringContainsString('project-billing@test.local', (new Crawler($row))->text());
        }
    }

    public function testFilterByIsBilled(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);

        // Assert on the rendered flag rather than a row count: nothing wraps these tests in a
        // transaction, so an earlier test billing a worklog would move any fixture total.
        $crawler = $this->filter($client, ['isBilled' => '1']);
        $billed = $crawler->filter('tbody tr[data-worklog-id] td:nth-child(6)');
        $this->assertGreaterThan(0, $billed->count());
        foreach ($billed as $cell) {
            $this->assertSame('Ja', trim((new Crawler($cell))->text()));
        }

        $crawler = $this->filter($client, ['isBilled' => '0']);
        $unbilled = $crawler->filter('tbody tr[data-worklog-id] td:nth-child(6)');
        $this->assertGreaterThan(0, $unbilled->count());
        foreach ($unbilled as $cell) {
            $this->assertSame('Nej', trim((new Crawler($cell))->text()));
        }
    }

    public function testPeriodToIncludesTheSelectedDay(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $started = $this->uniqueWorklog()->getStarted();
        $this->assertNotNull($started);

        $day = $started->format('Y-m-d');
        $dayBefore = (new \DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d');

        $crawler = $this->filter($client, [
            'search' => self::UNIQUE_WORKLOG,
            'periodFrom' => $day,
            'periodTo' => $day,
        ]);
        $this->assertCount(1, $crawler->filter('tbody tr[data-worklog-id]'), 'periodTo must include the selected day');

        $crawler = $this->filter($client, [
            'search' => self::UNIQUE_WORKLOG,
            'periodTo' => $dayBefore,
        ]);
        $this->assertCount(0, $crawler->filter('tbody tr[data-worklog-id]'));
    }

    public function testWorklogDeletedInTheSourceIsMarked(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $worklog = $this->uniqueWorklog();

        $worklog->setSourceDeletedDate(new \DateTime());
        $em->flush();

        try {
            $crawler = $this->filter($client, ['search' => self::UNIQUE_WORKLOG]);
            $row = $crawler->filter('tbody tr[data-worklog-id]');

            $this->assertCount(1, $row);
            $this->assertStringContainsString('line-through', (string) $row->attr('class'));
        } finally {
            // Nothing wraps these tests in a transaction, so undo the write.
            $worklog->setSourceDeletedDate(null);
            $em->flush();
        }
    }

    public function testDataProviderCredentialsAreNeverRendered(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $client->request('GET', self::URL);

        $this->assertStringNotContainsString('Not so secret', (string) $client->getResponse()->getContent());
    }

    /**
     * @param array<string, string> $filter
     */
    private function filter(KernelBrowser $client, array $filter): Crawler
    {
        $crawler = $client->request('GET', self::URL, ['worklog_filter' => $filter]);
        $this->assertResponseIsSuccessful();

        return $crawler;
    }

    private function uniqueWorklog(): Worklog
    {
        /** @var WorklogRepository $repository */
        $repository = static::getContainer()->get(WorklogRepository::class);
        $worklog = $repository->findOneBy(['projectTrackerIssueId' => self::UNIQUE_WORKLOG]);
        $this->assertNotNull($worklog);

        return $worklog;
    }
}
