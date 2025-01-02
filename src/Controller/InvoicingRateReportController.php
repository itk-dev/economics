<?php

namespace App\Controller;

use App\Form\InvoicingRateReportType;
use App\Model\Reports\InvoicingRateReportFormData;
use App\Model\Reports\InvoicingRateReportViewModeEnum;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Service\InvoicingRateReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/invoicing_rate_report')]
#[IsGranted('ROLE_REPORT')]
class InvoicingRateReportController extends AbstractController
{
    public function __construct(
        private readonly InvoicingRateReportService $invoicingRateReportService,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/', name: 'app_invoicing_rate_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $error = null;
        $mode = 'invoicing_rate_report';
        $reportFormData = new InvoicingRateReportFormData();

        $form = $this->createForm(InvoicingRateReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_invoicing_rate_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'sprint_report',
            ],
            'years' => [
                (new \DateTime())->modify('-1 year')->format('Y'),
                (new \DateTime())->format('Y'),
            ],
            'csrf_protection' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $viewPeriodType = $form->get('viewPeriodType')->getData() ?? PeriodTypeEnum::WEEK;
            $viewMode = InvoicingRateReportViewModeEnum::SUMMARY;
            $year = $form->get('year')->getData();
            $includeIssues = $form->get('includeIssues')->getData();

            try {
                $reportData = $this->invoicingRateReportService->getInvoicingRateReport($year, $viewPeriodType, $viewMode, $includeIssues);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'InvoicingRateReportController',
            'form' => $form,
            'error' => $error,
            'data' => $reportData,
            'mode' => $mode,
        ]);
    }
}
