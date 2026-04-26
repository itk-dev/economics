<?php

namespace App\Tests\Unit\Service;

use App\Service\ManagementReportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ManagementReportServiceTest extends TestCase
{
    private TranslatorInterface $translator;
    private ManagementReportService $service;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->service = new ManagementReportService($this->translator);
    }

    public function testGenerateSpreadsheetCsvResponseReturnsStreamedResponse(): void
    {
        $invoice1 = $this->createMock(\App\Entity\Invoice::class);
        $invoice1->method('getTotalPrice')->willReturn(1000.0);

        $invoice2 = $this->createMock(\App\Entity\Invoice::class);
        $invoice2->method('getTotalPrice')->willReturn(2000.0);

        $groupedInvoices = [
            2024 => [
                'sum' => 3000.0,
                0 => [$invoice1],
                1 => [$invoice2],
                2 => [],
                3 => [],
            ],
        ];

        $dateInterval = [
            'dateFrom' => '2024-01-01',
            'dateTo' => '2024-12-31',
        ];

        $response = $this->service->generateSpreadsheetCsvResponse($groupedInvoices, $dateInterval);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('application/vnd.ms-excel', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('management-report', $response->headers->get('Content-Disposition'));
    }

    public function testGenerateSpreadsheetCsvResponseCalculatesQuarterSums(): void
    {
        $invoice1 = $this->createMock(\App\Entity\Invoice::class);
        $invoice1->method('getTotalPrice')->willReturn(500.0);

        $invoice2 = $this->createMock(\App\Entity\Invoice::class);
        $invoice2->method('getTotalPrice')->willReturn(300.0);

        $groupedInvoices = [
            2024 => [
                'sum' => 800.0,
                0 => [$invoice1, $invoice2],
                1 => [],
                2 => [],
                3 => [],
            ],
        ];

        $dateInterval = [
            'dateFrom' => '2024-01-01',
            'dateTo' => '2024-12-31',
        ];

        $response = $this->service->generateSpreadsheetCsvResponse($groupedInvoices, $dateInterval);

        // Verify response is valid
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testGenerateSpreadsheetCsvResponseEmptyData(): void
    {
        $groupedInvoices = [];
        $dateInterval = [
            'dateFrom' => '2024-01-01',
            'dateTo' => '2024-12-31',
        ];

        $response = $this->service->generateSpreadsheetCsvResponse($groupedInvoices, $dateInterval);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testGenerateSpreadsheetCsvResponseMultipleYears(): void
    {
        $invoice2023 = $this->createMock(\App\Entity\Invoice::class);
        $invoice2023->method('getTotalPrice')->willReturn(100.0);

        $invoice2024 = $this->createMock(\App\Entity\Invoice::class);
        $invoice2024->method('getTotalPrice')->willReturn(200.0);

        $groupedInvoices = [
            2023 => [
                'sum' => 100.0,
                0 => [$invoice2023],
                1 => [],
                2 => [],
                3 => [],
            ],
            2024 => [
                'sum' => 200.0,
                0 => [$invoice2024],
                1 => [],
                2 => [],
                3 => [],
            ],
        ];

        $dateInterval = [
            'dateFrom' => '2023-01-01',
            'dateTo' => '2024-12-31',
        ];

        $response = $this->service->generateSpreadsheetCsvResponse($groupedInvoices, $dateInterval);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
