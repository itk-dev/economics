<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\WorkloadReportType;
use App\Model\Reports\WorkloadReportFormData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Service\ReportContextFactory;
use App\Service\ReportRenderer;
use App\Service\WorkloadReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/workload_report')]
#[IsGranted('ROLE_REPORT')]
class WorkloadReportController extends AbstractController
{
    public function __construct(
        private readonly WorkloadReportService $workloadReportService,
        private readonly ReportContextFactory $reportContextFactory,
        private readonly ReportRenderer $reportRenderer,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/', name: 'app_workload_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $error = null;
        $mode = 'workload_report';
        $reportFormData = new WorkloadReportFormData();

        $form = $this->createForm(WorkloadReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_workload_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'report',
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
            $viewMode = $form->get('viewMode')->getData() ?? ViewModeEnum::WORKLOAD;
            $year = $form->get('year')->getData();

            /** @var User|null $user */
            $user = $this->getUser();
            $context = $this->reportContextFactory->fromForm($reportFormData, $user, (int) $year);

            try {
                $reportData = $this->workloadReportService->getWorkloadReport($context, $viewPeriodType, $viewMode);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->reportRenderer->render(
            'reports/reports.html.twig',
            'reports/workload_report.html.twig',
            [
                'controller_name' => 'WorkloadReportController',
                'form' => $form,
                'error' => $error,
                'data' => $reportData,
                'mode' => $mode,
            ],
        );
    }
}
