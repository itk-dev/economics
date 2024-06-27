<?php

namespace App\Controller;

use App\Form\WorkloadReportType;
use App\Model\Reports\WorkloadReportFormData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
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
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly WorkloadReportService $workloadReportService,
    ) {
    }

    /**
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
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

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'WorkloadReportController',
            'form' => $form,
            'error' => $error,
            'data' => $reportData,
            'mode' => $mode,
        ]);
    }
}
