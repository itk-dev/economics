<?php

namespace App\Controller;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\WorkloadReportType;
use App\Model\Reports\WorkloadReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use App\Service\WorkloadReportService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
        private readonly ?string $defaultDataProvider,
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

        $dataProviders = $this->dataProviderRepository->findAll();
        $defaultProvider = $this->dataProviderRepository->find($this->defaultDataProvider);

        if (null === $defaultProvider && count($dataProviders) > 0) {
            $defaultProvider = $dataProviders[0];
        }

        $form = $this->createForm(WorkloadReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_workload_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'sprint_report',
            ],
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
        ]);

        $form->add('dataProvider', EntityType::class, [
            'class' => DataProvider::class,
            'required' => false,
            'label' => 'reports.workload_report.select_data_provider',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'onchange' => 'this.form.submit()',
                'class' => 'form-element',
            ],
            'data' => $this->dataProviderRepository->find($this->defaultDataProvider),
            'choices' => $dataProviders,
        ]);

        $form->add('viewPeriodType', ChoiceType::class, [
            'required' => false,
            'label' => 'reports.workload_report.select_view_period_type',
            'label_attr' => ['class' => 'label'],
            'placeholder' => false,
            'attr' => [
                'class' => 'form-element',
            ],
            'choices' => $this->workloadReportService->getViewPeriodTypes(),
        ]);

        $form->add('viewMode', ChoiceType::class, [
            'required' => false,
            'label' => 'reports.workload_report.select_viewmode',
            'label_attr' => ['class' => 'label'],
            'placeholder' => false,
            'attr' => [
                'class' => 'form-element',
            ],
            'choices' => $this->workloadReportService->getViewModes(),
        ]);

        $form->add('submit', ButtonType::class, [
            'block_name' => 'Submit',
            'attr' => [
                'onclick' => 'this.form.submit()',
                'class' => 'hour-report-submit button',
            ],
        ]);

        $form->handleRequest($request);

        $requestData = $request->query->all('workload_report');

        if (!empty($requestData['dataProvider'])) {
            $dataProvider = $this->dataProviderRepository->find($requestData['dataProvider']);

            if ($form->isSubmitted() && $form->isValid()) {
                $selectedDataProvider = $form->get('dataProvider')->getData() ?? $dataProvider;
                $viewPeriodType = $form->get('viewPeriodType')->getData() ?? 'week';
                $viewMode = $form->get('viewMode')->getData() ?? 'percentage_logged';

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
