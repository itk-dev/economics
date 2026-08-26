<?php

namespace App\Controller;

use App\Form\WorkloadReportType;
use App\Model\Reports\WorkloadReportFormData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Repository\WorkerRepository;
use App\Service\WorkloadReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/workload_report')]
#[IsGranted('ROLE_REPORT')]
class WorkloadReportController extends AbstractController
{
    public function __construct(
        private readonly WorkloadReportService $workloadReportService,
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

            try {
                $reportData = $this->workloadReportService->getWorkloadReport($year, $viewPeriodType, $viewMode);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'WorkloadReportController',
            'form' => $form,
            'error' => $error,
            'data' => $reportData,
            'mode' => $mode,
        ]);
    }

    /**
     * Renders the worklogs behind a single report cell, as the body of the drill-down modal.
     *
     * Returns a fragment rather than a page: it is fetched into a <dialog> on the report itself,
     * so the table keeps its horizontal scroll position.
     */
    #[Route('/worklogs', name: 'app_workload_report_worklogs', methods: ['GET'])]
    public function worklogs(Request $request, WorkerRepository $workerRepository): Response
    {
        $year = $request->query->get('year');
        $period = $request->query->get('period');
        $viewPeriodType = PeriodTypeEnum::tryFrom((string) $request->query->get('periodType'));
        $viewMode = ViewModeEnum::tryFrom((string) $request->query->get('viewMode'));

        if (null === $viewPeriodType || null === $viewMode || !is_numeric($year) || !is_numeric($period)) {
            throw new BadRequestHttpException('Invalid workload report cell.');
        }

        $worker = $workerRepository->findOneBy([
            'email' => (string) $request->query->get('worker'),
            'includeInReports' => true,
        ]);

        if (null === $worker) {
            throw $this->createNotFoundException('Worker not found.');
        }

        return $this->render('reports/workload_report_worklogs.html.twig', [
            'data' => $this->workloadReportService->getPeriodWorklogs($worker, (int) $year, $viewPeriodType, $viewMode, (int) $period),
        ]);
    }
}
