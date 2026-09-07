<?php

namespace App\Tests\Unit\Service;

use App\Model\Invoices\WorklogFilterData;
use App\Repository\WorklogRepository;
use App\Service\WorklogExportService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Pins how a repository row becomes a CSV line: column order, the Danish number and date
 * formatting, and the filter being handed straight through to the streamed walk.
 */
class WorklogExportServiceTest extends TestCase
{
    private WorklogRepository&\PHPUnit\Framework\MockObject\MockObject $worklogRepository;
    private WorklogExportService $service;

    protected function setUp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->worklogRepository = $this->createMock(WorklogRepository::class);

        $this->service = new WorklogExportService($this->worklogRepository, $translator);
    }

    public function testItReturnsACsvAttachment(): void
    {
        $this->willStream();

        $response = $this->service->generateCsvResponse(new WorklogFilterData());

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="worklogs-\d{4}-\d{2}-\d{2}_\d{6}\.csv"$/',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function testTheHeaderRowNamesEveryColumnInOrder(): void
    {
        $this->willStream();

        $header = $this->lines($this->render(new WorklogFilterData()))[0];

        $this->assertSame(
            'worklog.date;worklogs.export_issue;worklogs.export_description;worklog.worker;'
            .'worklogs.project;worklogs.data_provider;worklog.is_billed;worklog.epic;'
            .'worklog.version;worklog.time_spent;worklogs.invoice_entry',
            $header
        );
    }

    public function testARowIsFormattedTheWayTheListPageShowsIt(): void
    {
        $this->willStream([$this->row()]);

        $row = $this->lines($this->render(new WorklogFilterData()))[1];

        // Only cells that need it are enclosed, which is why a description containing the
        // delimiter or a newline is still safe.
        $this->assertSame(
            '20/04/2026;EXP-tagged;"Opsætning af båd";alice@test.local;"Export project";'
            .'"Export provider";worklog.is_billed_true;"Epic one, Epic two";EXP-1;1,50;"Invoice 1"',
            $row
        );
    }

    public function testHoursUseADanishDecimalComma(): void
    {
        $this->willStream([
            $this->row(['timeSpentSeconds' => 3600]),
            $this->row(['timeSpentSeconds' => 5400]),
            $this->row(['timeSpentSeconds' => 900]),
        ]);

        $hours = array_map(
            fn (string $line): string => explode(';', $line)[9],
            \array_slice($this->lines($this->render(new WorklogFilterData())), 1)
        );

        $this->assertSame(['1,00', '1,50', '0,25'], $hours);
    }

    public function testAnUnbilledWorklogUsesTheNegativeLabel(): void
    {
        $this->willStream([
            $this->row(['isBilled' => false]),
            $this->row(['isBilled' => null]),
        ]);

        foreach (\array_slice($this->lines($this->render(new WorklogFilterData())), 1) as $line) {
            $this->assertSame('worklog.is_billed_false', explode(';', $line)[6]);
        }
    }

    public function testNullColumnsBecomeEmptyCells(): void
    {
        $this->willStream([
            $this->row([
                'projectName' => null,
                'dataProviderName' => null,
                'invoiceName' => null,
                'epics' => null,
                'versions' => null,
            ]),
        ]);

        $cells = explode(';', $this->lines($this->render(new WorklogFilterData()))[1]);

        $this->assertSame('', $cells[4]);
        $this->assertSame('', $cells[5]);
        $this->assertSame('', $cells[7]);
        $this->assertSame('', $cells[8]);
        $this->assertSame('', $cells[10]);
    }

    public function testTheFilterIsPassedStraightToTheStreamedWalk(): void
    {
        $filterData = new WorklogFilterData();
        $filterData->search = 'needle';

        $this->worklogRepository
            ->expects($this->once())
            ->method('streamFilteredForExport')
            ->with($this->identicalTo($filterData))
            ->willReturnCallback(static function (): \Generator {
                yield from [];
            });

        $this->render($filterData);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function willStream(array $rows = []): void
    {
        $this->worklogRepository
            ->method('streamFilteredForExport')
            ->willReturnCallback(static function () use ($rows): \Generator {
                yield from $rows;
            });
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'started' => new \DateTime('2026-04-20 09:00:00'),
            'description' => 'Opsætning af båd',
            'worker' => 'alice@test.local',
            'isBilled' => true,
            'timeSpentSeconds' => 5400,
            'issueName' => 'EXP-tagged',
            'projectName' => 'Export project',
            'dataProviderName' => 'Export provider',
            'invoiceName' => 'Invoice 1',
            'epics' => 'Epic one, Epic two',
            'versions' => 'EXP-1',
        ], $overrides);
    }

    private function render(WorklogFilterData $filterData): string
    {
        $response = $this->service->generateCsvResponse($filterData);

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $csv): array
    {
        return preg_split('/\R/', trim(str_replace("\xEF\xBB\xBF", '', $csv))) ?: [];
    }
}
