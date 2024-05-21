<?php

namespace App\Controller;

use App\Entity\DataProvider;
use App\Model\Reports\ReportsFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use App\Service\ViewService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports')]
class HourReportController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ViewService $viewService,
        private readonly ?string $defaultDataProvider,
    ) {
    }

    #[Route('/', name: 'app_hour_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $mode = 'reports';
        $reportFormData = new ReportsFormData();

        $form = $this->createForm(ReportsFormData::class, $reportFormData, [
            'action' => $this->generateUrl('app_hour_report', $this->viewService->addView([])),
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
            'label' => 'reports.hour.select_data_provider',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'onchange' => 'this.form.submit()',
                'class' => 'form-element',
            ],
            'help' => 'sprint_report.data_provider_helptext',
            'choices' => $this->dataProviderRepository->findAll(),
        ]);
        $form->add('projectId', ChoiceType::class, [
            'placeholder' => 'reports.hour.select_option',
            'choices' => [],
            'required' => false,
            'label' => 'reports.hour.select_project',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'disabled' => true,
                'class' => 'form-element',
            ],
        ]);
        $form->add('milestoneId', ChoiceType::class, [
            'placeholder' => 'reports.hour.select_option',
            'choices' => [],
            'required' => false,
            'label' => 'reports.hour.select_milestone',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'disabled' => true,
                'class' => 'form-element',
            ],
        ]);

        $requestData = $request->query->all('reports_form_data');

        if (isset($requestData['sprint_report'])) {
            $requestData = $requestData['sprint_report'];
        }
        if (!empty($requestData['dataProvider'])) {
            $dataProvider = $this->dataProviderRepository->find($requestData['dataProvider']);

            if (null != $dataProvider) {
                $service = $this->dataProviderService->getService($dataProvider);

                $projectCollection = $service->getSprintReportProjects();

                $projectChoices = [];

                foreach ($projectCollection->projects as $project) {
                    $projectChoices[$project->name] = $project->id;
                }

                // Override projectId with element with choices.
                $form->add('projectId', ChoiceType::class, [
                    'placeholder' => 'sprint_report.select_an_option',
                    'choices' => $projectChoices,
                    'required' => false,
                    'label' => 'sprint_report.select_project',
                    'label_attr' => ['class' => 'label'],
                    'row_attr' => ['class' => 'form-choices'],
                    'attr' => [
                        'class' => 'form-element',
                        'data-sprint-report-target' => 'project',
                        'data-action' => 'sprint-report#submitFormProjectId',
                        'data-choices-target' => 'choices',
                        'onchange' => 'this.form.submit()',
                    ],
                ]);
            }
        }
        if (!empty($requestData['dataProvider']) && !empty($requestData['projectId'])) {
            $service = $this->dataProviderService->getService($dataProvider);
            $projectId = $requestData['projectId'];

            $milestoneCollection = $service->getSprintReportVersions($projectId);
            $milestoneChoices = [];
            $milestoneChoices['All milestones'] = 0;
            foreach ($milestoneCollection->versions as $milestone) {
                $milestoneChoices[$milestone->name] = $milestone->id;
            }

            // Override projectId with element with choices.
            $form->add('milestoneId', ChoiceType::class, [
                'placeholder' => 'sprint_report.select_an_option',
                'choices' => $milestoneChoices,
                'required' => false,
                'label' => 'sprint_report.select_project',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-choices'],
                'attr' => [
                    'class' => 'form-element',
                    'data-sprint-report-target' => 'project',
                    'data-action' => 'sprint-report#submitFormMilestoneId',
                    'data-choices-target' => 'choices',
                    'onchange' => 'this.form.submit()',
                ],
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectId = $form->get('projectId')->getData();
            $milestoneId = $form->has('milestoneId') ? $form->get('milestoneId')->getData() : null;
            $dataProvider = $form->get('dataProvider')->getData();

            if (!empty($milestoneId) && !empty($projectId) && !empty($dataProvider)) {
                $service = $this->dataProviderService->getService($dataProvider);
                $reportData = $service?->getHourReportData($projectId, $milestoneId);
                $mode = 'hourReport';
            }

            if (!empty($projectId) && !empty($dataProvider) && 0 === $milestoneId) {
                $service = $this->dataProviderService->getService($dataProvider);
                $reportData = $service?->getHourReportData($projectId);
                $mode = 'hourReport';
            }
        }

        return $this->render('reports/reports.html.twig', $this->viewService->addView([
            'controller_name' => 'HourReportController',
            'form' => $form,
            'error' => $error ?? null,
            'data' => $reportData,
            'mode' => $mode,
        ]));
    }
}
