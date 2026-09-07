<?php

namespace App\Service;

use App\Model\Invoices\WorklogFilterData;
use App\Repository\WorklogRepository;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options;
use OpenSpout\Writer\CSV\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Streams the admin worklog list, as filtered, to a CSV download.
 *
 * Rows are written to php://output as the repository yields them, so neither the document nor the
 * result set is ever held in memory — unlike BillingService, which buffers its whole file twice to
 * re-encode it. That is what makes exporting every page of an unbounded filter safe.
 */
readonly class WorklogExportService
{
    public function __construct(
        private WorklogRepository $worklogRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function generateCsvResponse(WorklogFilterData $filterData): StreamedResponse
    {
        $response = new StreamedResponse();

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="worklogs-%s.csv"', date('Y-m-d_His'))
        );
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        $response->setCallback(function () use ($filterData): void {
            // Exporting an unfiltered table is a legitimately long request, and nothing in this
            // project raises max_execution_time.
            set_time_limit(0);

            $options = new Options();
            // Semicolon plus the BOM is what Danish Excel needs to split the columns and keep æøå
            // intact. The default enclosure quotes any description containing one of them.
            $options->FIELD_DELIMITER = ';';
            $options->SHOULD_ADD_BOM = true;

            $writer = new Writer($options);
            $writer->openToFile('php://output');

            try {
                $writer->addRow(Row::fromValues($this->headerRow()));

                foreach ($this->worklogRepository->streamFilteredForExport($filterData) as $row) {
                    $writer->addRow(Row::fromValues($this->formatRow($row)));
                }
            } finally {
                $writer->close();
            }
        });

        return $response;
    }

    /**
     * Column labels, reusing the keys the list page's table headers already use so the two cannot
     * drift apart.
     *
     * @return list<string>
     */
    private function headerRow(): array
    {
        return array_map(
            fn (string $key): string => $this->translator->trans($key),
            [
                'worklog.date',
                'worklogs.export_issue',
                'worklogs.export_issue_id',
                'worklogs.export_description',
                'worklog.worker',
                'worklogs.project',
                'worklogs.data_provider',
                'worklog.is_billed',
                'worklog.epic',
                'worklog.version',
                'worklog.time_spent',
                'worklogs.invoice_entry',
            ]
        );
    }

    /**
     * @param array<string, mixed> $row a row as yielded by WorklogRepository::streamFilteredForExport
     *
     * @return list<string>
     */
    private function formatRow(array $row): array
    {
        $started = $row['started'];
        $timeSpentSeconds = (int) $row['timeSpentSeconds'];

        return [
            $started instanceof \DateTimeInterface ? $started->format('d/m/Y') : '',
            $this->text($row['issueName']),
            // The free-text filter searches this, so it belongs in the file: without it nothing
            // explains why a row matched an issue-number search.
            $this->text($row['issueId']),
            $this->text($row['description']),
            $this->text($row['worker']),
            $this->text($row['projectName']),
            $this->text($row['dataProviderName']),
            $this->translator->trans($row['isBilled'] ? 'worklog.is_billed_true' : 'worklog.is_billed_false'),
            $this->text($row['epics']),
            $this->text($row['versions']),
            // Danish decimal comma: with a semicolon delimiter this is what Excel reads back as a
            // number rather than as text.
            number_format($timeSpentSeconds / 3600, 2, ',', ''),
            $this->text($row['invoiceName']),
        ];
    }

    private function text(mixed $value): string
    {
        return null === $value ? '' : (string) $value;
    }
}
