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
        $dataProvider = static::getContainer()->get(DataProviderRepository::class)
            ->findOneBy(['name' => 'Data Provider 2 - Leantime 2']);
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
        $project = static::getContainer()->get(ProjectRepository::class)
            ->findOneBy(['name' => 'project-0-3']);
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
        $worker = static::getContainer()->get(WorkerRepository::class)
            ->findOneBy(['email' => 'project-billing@test.local']);
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

        // AppFixtures bills exactly ten worklogs; everything else is false, never null.
        $crawler = $this->filter($client, ['isBilled' => '1']);
        $this->assertCount(10, $crawler->filter('tbody tr[data-worklog-id]'));

        $crawler = $this->filter($client, ['isBilled' => '0', 'search' => self::UNIQUE_WORKLOG]);
        $this->assertCount(1, $crawler->filter('tbody tr[data-worklog-id]'));
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
        $worklog = static::getContainer()->get(WorklogRepository::class)
            ->findOneBy(['projectTrackerIssueId' => self::UNIQUE_WORKLOG]);
        $this->assertNotNull($worklog);

        return $worklog;
    }
}
