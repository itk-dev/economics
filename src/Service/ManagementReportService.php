<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ManagementReportService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function generateSpreadsheetCsvResponse(array $groupedInvoices, $dateInterval): Response
    {
        // Add header.
        $values[] = [
            $this->translator->trans('reports.management-year'),
            $this->translator->trans('reports.management-total'),
            $this->translator->trans('reports.management-1st-quarter'),
            $this->translator->trans('reports.management-2nd-quarter'),
            $this->translator->trans('reports.management-3rd-quarter'),
            $this->translator->trans('reports.management-4th-quarter'),
        ];

        foreach ($groupedInvoices as $key => $yearValues) {
            $values[] = [
                $key,
                $yearValues['sum'],
                $yearValues[0] = $this->calculateYear($yearValues[0]),
                $yearValues[1] = $this->calculateYear($yearValues[1]),
                $yearValues[2] = $this->calculateYear($yearValues[2]),
                $yearValues[3] = $this->calculateYear($yearValues[3]),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($values, null, 'A1');

        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse();
        $filename = 'management-report--'.$dateInterval['dateFrom'].'-'.$dateInterval['dateTo'].'.csv';

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        $response->setCallback(function () use ($writer) {
            $writer->save('php://output');
        });

        return $response;
    }

    private function calculateYear($quarterValues): string
    {
        $sum = 0;
        foreach ($quarterValues as $invoice) {
            $sum += $invoice->getTotalPrice();
        }

        return (string) $sum;
    }
}
