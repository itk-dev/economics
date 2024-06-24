<?php

namespace App\Tests\Controller;

use App\Controller\WorkloadReportController;
use App\Model\Reports\WorkloadReportFormData;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkloadReportControllerTest extends WebTestCase
{
    private WorkloadReportController $workloadReportController;
    private Request $request;
    private WorkloadReportFormData $formData;

    protected function setUp(): void
    {
        $this->workloadReportController = new WorkloadReportController();
        $this->request = new Request();
        $this->formData = new WorkloadReportFormData();
    }

    public function testIndex(): void
    {
        $response = $this->workloadReportController->index($this->request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testIndexWithData(): void
    {
        $this->request->request->set('workload_report', $this->formData);
        $response = $this->workloadReportController->index($this->request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testIndexWithInvalidData(): void
    {
        $this->formData->dataProvider = null;
        $this->request->request->set('workload_report', $this->formData);
    }
}
