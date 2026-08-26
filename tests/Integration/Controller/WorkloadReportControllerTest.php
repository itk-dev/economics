<?php

namespace App\Tests\Integration\Controller;

class WorkloadReportControllerTest extends AbstractControllerTestCase
{
    private const WORKLOGS_URL = '/admin/reports/workload_report/worklogs?worker=report%40test.local&year=2024&periodType=week&period=1&viewMode=workload';

    public function testIndexSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            '/admin/reports/workload_report/',
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }

    public function testWorklogsSmokeMatrix(): void
    {
        $this->assertSmokeMatrix(
            self::WORKLOGS_URL,
            allowedRoles: ['ROLE_REPORT'],
            deniedRoles: ['ROLE_INVOICE'],
        );
    }

    public function testWorklogsRejectsAnUnknownPeriodType(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', '/admin/reports/workload_report/worklogs?worker=report%40test.local&year=2024&periodType=fortnight&period=1&viewMode=workload');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWorklogsRejectsANonNumericPeriod(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', '/admin/reports/workload_report/worklogs?worker=report%40test.local&year=2024&periodType=week&period=notanumber&viewMode=workload');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWorklogsReturnsNotFoundForAnUnknownWorker(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_REPORT']);
        $client->request('GET', '/admin/reports/workload_report/worklogs?worker=nobody%40test.local&year=2024&periodType=week&period=1&viewMode=workload');

        $this->assertResponseStatusCodeSame(404);
    }
}
