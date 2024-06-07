<?php

namespace App\Controller;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Form\HourReportType;
use App\Model\Reports\HourReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use App\Service\HourReportService;
use App\Service\ViewService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports/hour_report')]
class HourReportController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ViewService $viewService,
        private readonly HourReportService $hourReportService,
        private readonly ?string $defaultDataProvider,
    ) {
    }

    /**
     * @throws EconomicsException
     * @throws \Exception
     */
    #[Route('/', name: 'app_hour_report')]
    public function index(Request $request): Response
    {
        $reportData = null;

        $mode = 'reports';
        $error = null;
        $reportFormData = new HourReportFormData();

        $form = $this->createForm(HourReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_hour_report', $this->viewService->addView([])),
            'method' => 'GET',
            'attr' => [
                'id' => 'hour_report',
            ],
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
        ]);

        $form->add('dataProvider', EntityType::class, [
            'class' => DataProvider::class,
            'required' => true,
            'label' => 'reports.hour_report.select_data_provider',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'onchange' => 'this.form.submit()',
                'class' => 'form-element',
                'data-preselect' => $this->defaultDataProvider ?? '',
            ],
            'help' => 'sprint_report.data_provider_helptext',
            'choices' => $this->dataProviderRepository->findAll(),
        ]);
        $form->add('projectId', ChoiceType::class, [
            'placeholder' => 'reports.hour_report.select_option',
            'choices' => [],
            'required' => true,
            'label' => 'reports.hour_report.select_project',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'disabled' => true,
                'class' => 'form-element',
            ],
        ]);
        $form->add('versionId', ChoiceType::class, [
            'placeholder' => 'reports.hour_report.select_option',
            'choices' => [],
            'required' => true,
            'label' => 'reports.hour_report.select_milestone',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'disabled' => true,
                'class' => 'form-element',
            ],
        ]);

        $requestData = $request->query->all('hour_report');

        if (isset($requestData['sprint_report'])) {
            $requestData = $requestData['sprint_report'];
        }

        if (!empty($requestData['dataProvider']) || $this->defaultDataProvider) {
            if (!empty($requestData['dataProvider'])) {
                $dataProvider = $this->dataProviderRepository->find($requestData['dataProvider']);
            } else {
                $dataProvider = $this->dataProviderRepository->find($this->defaultDataProvider);
            }

            if (null != $dataProvider) {
                $projectChoices = $this->hourReportService->getProjects();

                // Override projectId with element with choices.
                $form->add('projectId', ChoiceType::class, [
                    'placeholder' => 'sprint_report.select_an_option',
                    'choices' => $projectChoices,
                    'required' => true,
                    'label' => 'sprint_report.select_project',
                    'label_attr' => ['class' => 'label'],
                    'row_attr' => ['class' => 'form-choices'],
                    'attr' => [
                        'class' => 'form-element',
                        'onchange' => 'this.form.submit()',
                    ],
                ]);
            }
        }
        if ((!empty($requestData['dataProvider']) || $this->defaultDataProvider) && !empty($requestData['projectId'])) {
            $projectId = $requestData['projectId'];

            $milestoneChoices = $this->hourReportService->getMilestones($projectId, true);

            // Override projectId with element with choices.
            $form->add('versionId', ChoiceType::class, [
                'placeholder' => 'reports.hour_report.select_an_option',
                'choices' => $milestoneChoices,
                'required' => true,
                'label' => 'reports.hour_report.select_milestone',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-choices'],
                'attr' => [
                    'class' => 'form-element',
                ],
            ]);
            $form->add('fromDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'reports.hour_report.select_fromdate',
                'label_attr' => ['class' => 'label'],
                'empty_data' => '',
                'by_reference' => true,
                'attr' => [
                    'class' => 'form-element',
                    'data-preselect-date' => $this->hourReportService->getFromDate(),
                ],
            ]);
            $form->add('toDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'reports.hour_report.select_todate',
                'label_attr' => ['class' => 'label'],
                'empty_data' => '',
                'by_reference' => true,
                'attr' => [
                    'class' => 'form-element disabled',
                    'data-preselect-date' => $this->hourReportService->getToDate(),
                ],
            ]);
            $form->add('submit', ButtonType::class, [
                'block_name' => 'Submit',
                'attr' => [
                    'onclick' => 'this.form.submit()',
                    'class' => 'hour-report-submit button',
                ],
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectId = $form->get('projectId')->getData();
            $milestoneId = $form->get('versionId')->getData();
            $selectedDataProvider = $form->get('dataProvider')->getData() ?? $dataProvider ?? null;
            $fromDate = $form->has('fromDate') ? $form->get('fromDate')->getData() : new \DateTime($this->hourReportService->getFromDate());
            $toDate = $form->has('toDate') ? $form->get('toDate')->getData() : new \DateTime($this->hourReportService->getToDate());

            if (!empty($milestoneId) && !empty($projectId) && !empty($dataProvider)) {
                $reportData = $this->hourReportService->getHourReport($projectId, $fromDate, $toDate, $milestoneId);
                $mode = 'hourReport';
            }

            // If milestone is '0', it will evaluate as empty above, but really we want to get the report for all milestones
            if (!empty($projectId) && !empty($selectedDataProvider) && '0' === $milestoneId) {
                $reportData = $this->hourReportService->getHourReport($projectId, $fromDate, $toDate);
                $mode = 'hourReport';
            }
        }

        return $this->render('reports/reports.html.twig', $this->viewService->addView([
            'controller_name' => 'HourReportController',
            'form' => $form,
            'data' => $reportData,
            'mode' => $mode,
            'error' => $error,
        ]));
    }
}
