<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Project;
use App\Model\Invoices\WorklogFilterData;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Covers the worklog CSV export end to end: the route honours the list's filter, and the download
 * contains every page of it rather than just the 25 rows on screen.
 *
 * Read-only — nothing here writes, so there is nothing to clean up.
 */
class WorklogExportFlowTest extends AbstractControllerTestCase
{
    private const URL = '/admin/worklog/export';

    public function testTheExportIsAdminOnly(): void
    {
        $this->assertAnonymousRedirectsToLogin(self::URL);
        $this->assertDeniedFor(self::URL, ['ROLE_USER']);
    }

    public function testItDownloadsACsvAttachment(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $client->request('GET', self::URL, $this->filterQuery($this->aProjectWithSeveralPages($client)));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');

        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment;', $disposition);
        $this->assertStringContainsString('worklogs-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
        $this->assertStringNotContainsString('.csv ', $disposition, 'The filename must not carry trailing whitespace.');
    }

    public function testTheFileIsSemicolonSeparatedUtf8WithABom(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $project = $this->aProjectWithSeveralPages($client);

        $csv = $this->download($client, $this->filterQuery($project));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Danish Excel needs the BOM to read UTF-8.');

        $header = $this->lines($csv)[0];
        $this->assertStringContainsString('Dato;Opgave;Beskrivelse', $header);
        $this->assertStringContainsString('Timer', $header);
    }

    public function testEveryPageOfTheFilteredListIsInTheFile(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $project = $this->aProjectWithSeveralPages($client);

        $total = $this->totalFor($client, $project);
        $csv = $this->download($client, $this->filterQuery($project));

        // Minus the header row.
        $this->assertCount(
            $total,
            \array_slice($this->lines($csv), 1),
            'The export must hold every matching worklog, not just the first page.'
        );
        $this->assertGreaterThan(25, $total, 'Guard: this fixture project must span more than one page.');
    }

    public function testTheFilterNarrowsTheFile(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_ADMIN']);
        $project = $this->aProjectWithSeveralPages($client);

        $all = \count($this->lines($this->download($client, $this->filterQuery($project))));
        $narrowed = \count($this->lines($this->download($client, [
            'worklog_filter' => [
                'project' => (string) $project->getId(),
                'search' => 'no-fixture-worklog-matches-this',
            ],
        ])));

        $this->assertSame(1, $narrowed, 'A filter matching nothing leaves only the header row.');
        $this->assertGreaterThan($narrowed, $all);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function filterQuery(Project $project): array
    {
        return ['worklog_filter' => ['project' => (string) $project->getId()]];
    }

    /**
     * @param array<string, array<string, string>> $query
     */
    private function download(KernelBrowser $client, array $query): string
    {
        $client->request('GET', self::URL, $query);

        // The test client already ran the stream's callback and buffered the output; the response
        // itself refuses to stream twice, so read the captured copy rather than sendContent().
        return $client->getInternalResponse()->getContent();
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $csv): array
    {
        return preg_split('/\R/', trim($csv)) ?: [];
    }

    private function totalFor(KernelBrowser $client, Project $project): int
    {
        $filter = new WorklogFilterData();
        $filter->project = $project;

        return $this->worklogRepository($client)->getFilteredPagination($filter, 1)->getTotalItemCount();
    }

    /**
     * Picks a fixture project whose worklogs span more than the list's 25-row page, so the
     * "all pages" assertions have something to prove. Discovered rather than hard-coded, so
     * changing fixture volume cannot silently weaken the test.
     */
    private function aProjectWithSeveralPages(KernelBrowser $client): Project
    {
        $projectRepository = $client->getContainer()->get(ProjectRepository::class);
        \assert($projectRepository instanceof ProjectRepository);

        foreach ($projectRepository->findAll() as $project) {
            if ($this->totalFor($client, $project) > 25) {
                return $project;
            }
        }

        self::fail('No fixture project has more than one page of worklogs.');
    }

    private function worklogRepository(KernelBrowser $client): WorklogRepository
    {
        $repository = $client->getContainer()->get(WorklogRepository::class);
        \assert($repository instanceof WorklogRepository);

        return $repository;
    }
}
