<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Invoice;
use App\Entity\Project;

class ManagementReportFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_REPORT');
    }

    public function testCreateRendersTheDateIntervalForm(): void
    {
        $this->persistRecordedInvoice('Recorded invoice', new \DateTime('2026-02-15'), 1000.0);

        $crawler = $this->client->request('GET', '/admin/management-report');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[name="management_report_date_interval"]'));
    }

    public function testOutputRedirectsWithoutADateInterval(): void
    {
        $this->client->request('GET', '/admin/management-report/output');

        $this->assertResponseRedirects('/admin/management-report');
    }

    public function testOutputRedirectsWhenOnlyOneBoundIsGiven(): void
    {
        $this->client->request('GET', '/admin/management-report/output?'.http_build_query([
            'management_report_date_interval' => ['dateFrom' => '2026-01-01', 'dateTo' => ''],
        ]));

        $this->assertResponseRedirects('/admin/management-report');
    }

    public function testOutputGroupsRecordedInvoicesByYearAndQuarter(): void
    {
        $this->persistRecordedInvoice('Q1 invoice', new \DateTime('2026-02-15'), 1000.0);
        $this->persistRecordedInvoice('Q4 invoice', new \DateTime('2026-11-15'), 500.0);

        $crawler = $this->client->request('GET', '/admin/management-report/output?'.http_build_query([
            'management_report_date_interval' => ['dateFrom' => '2026-01-01', 'dateTo' => '2026-12-31'],
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('2026', $crawler->html());
    }

    public function testOutputIsEmptyForAPeriodWithoutInvoices(): void
    {
        $this->client->request('GET', '/admin/management-report/output?'.http_build_query([
            'management_report_date_interval' => ['dateFrom' => '1990-01-01', 'dateTo' => '1990-12-31'],
        ]));

        $this->assertResponseIsSuccessful();
    }

    public function testExportRedirectsWithoutADateInterval(): void
    {
        $this->client->request('GET', '/admin/management-report/output/export');

        $this->assertResponseRedirects('/admin/management-report');
    }

    public function testExportReturnsASpreadsheet(): void
    {
        $this->persistRecordedInvoice('Export invoice', new \DateTime('2026-03-15'), 2500.0);

        $this->client->request('GET', '/admin/management-report/output/export?'.http_build_query([
            'management_report_date_interval' => ['dateFrom' => '2026-01-01', 'dateTo' => '2026-12-31'],
        ]));

        $this->assertResponseIsSuccessful();

        $headers = $this->client->getResponse()->headers;
        $disposition = $this->requireString($headers->get('content-disposition'));
        $this->assertSame('application/vnd.ms-excel', $headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', $disposition);
        $this->assertStringContainsString('2026-01-01', $disposition);
    }

    public function testReportIsDeniedForOtherRoles(): void
    {
        $this->assertDeniedForRole('/admin/management-report', 'ROLE_INVOICE');
    }

    private function persistRecordedInvoice(string $name, \DateTime $recordedDate, float $totalPrice): void
    {
        $invoice = new Invoice();
        $invoice->setName($name);
        $invoice->setProject($this->findOne(Project::class));
        $invoice->setRecorded(true);
        $invoice->setRecordedDate($recordedDate);
        $invoice->setTotalPrice($totalPrice);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
