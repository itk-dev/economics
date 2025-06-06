<?php

namespace App\Controller;

use App\Form\BillableUnbilledHoursReportType;
use App\Model\Reports\BillableUnbilledHoursReportFormData;
use App\Service\BillableUnbilledHoursReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/billable_unbilled_hours_report')]
#[IsGranted('ROLE_REPORT')]
class BillableUnbilledHoursReportController extends AbstractController
{
    public function __construct(
        private readonly BillableUnbilledHoursReportService $billableUnbilledHoursReportService,
    ) {
    }

    #[Route('/', name: 'app_billable_unbilled_hours_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $error = null;
        $mode = 'billable_unbilled_hours_report';
        $reportFormData = new BillableUnbilledHoursReportFormData();

        $form = $this->createForm(BillableUnbilledHoursReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_billable_unbilled_hours_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'report',
            ],
            'years' => [
                (new \DateTime())->modify('-1 year')->format('Y'),
                (new \DateTime())->format('Y'),
            ],
            'quarters' => [
                'billable_unbilled_hours_report.quarters.all' => null,
                'billable_unbilled_hours_report.quarters.q1' => 1,
                'billable_unbilled_hours_report.quarters.q2' => 2,
                'billable_unbilled_hours_report.quarters.q3' => 3,
                'billable_unbilled_hours_report.quarters.q4' => 4,
            ],
            'csrf_protection' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $year = $form->get('year')->getData();
            $quarter = $form->get('quarter')->getData();

            try {
                $reportData = $this->billableUnbilledHoursReportService->getBillableUnbilledHoursReport($year, $quarter);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'BillableUnbilledHoursReportController',
            'form' => $form,
            'error' => $error,
            'data' => $reportData,
            'mode' => $mode,
        ]);
    }
}
