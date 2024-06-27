<?php

namespace App\Controller;

use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\WorkloadReportType;
use App\Model\Reports\WorkloadReportFormData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Repository\DataProviderRepository;
use App\Service\ViewService;
use App\Service\WorkloadReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports/workload_report')]
class WorkloadReportController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
        private readonly WorkloadReportService $workloadReportService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
    }

    #[Route('/', name: 'app_workload_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $error = null;
        $mode = 'workload_report';
        $reportFormData = new WorkloadReportFormData();

        $form = $this->createForm(WorkloadReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_workload_report', $this->viewService->addView([])),
            'method' => 'GET',
            'attr' => [
                'id' => 'sprint_report',
            ],
            'csrf_protection' => false,
        ]);

        $form->handleRequest($request);

        $requestData = $request->query->all('workload_report');

        if (!empty($requestData['dataProvider'])) {
            $dataProvider = $this->dataProviderRepository->find($requestData['dataProvider']);

            if ($form->isSubmitted() && $form->isValid()) {
                $selectedDataProvider = $form->get('dataProvider')->getData() ?? $dataProvider;
                $viewPeriodType = $form->get('viewPeriodType')->getData() ?? PeriodTypeEnum::WEEK;
                $viewMode = $form->get('viewMode')->getData() ?? ViewModeEnum::WORKLOAD;

                if ($selectedDataProvider) {
                    try {
                        $reportData = $this->workloadReportService->getWorkloadReport($viewPeriodType, $viewMode);
                    } catch (\Exception $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }

        return $this->render('reports/reports.html.twig', $this->viewService->addView([
            'controller_name' => 'WorkloadReportController',
            'form' => $form,
            'error' => $error,
            'data' => $reportData,
            'mode' => $mode,
        ]));
    }
}
