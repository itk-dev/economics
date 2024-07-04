<?php

namespace App\Controller;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\SprintReportType;
use App\Model\SprintReport\SprintReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use App\Service\SprintReportService;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/sprint-report')]
#[IsGranted('ROLE_REPORT')]
class SprintReportController extends AbstractController
{
    public function __construct(
        private readonly SprintReportService $sprintReportService,
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    #[Route('/', name: 'app_sprint_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $sprintReportFormData = new SprintReportFormData();

        $form = $this->createForm(SprintReportType::class, $sprintReportFormData, [
            'action' => $this->generateUrl('app_sprint_report', []),
            'method' => 'GET',
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
        ]);

        $form->add('dataProvider', EntityType::class, [
            'class' => DataProvider::class,
            'required' => false,
            'label' => 'sprint_report.data_provider',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'data-action' => 'sprint-report#submitFormProjectId',
                'class' => 'form-element',
            ],
            'help' => 'sprint_report.data_provider_helptext',
            'choices' => $this->dataProviderRepository->findAll(),
        ]);

        $requestData = $request->query->all();

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
                        'data-sprint-report-target' => 'project',
                        'data-action' => 'sprint-report#submitFormProjectId',
                        'data-choices-target' => 'choices',
                    ],
                ]);

                if (!empty($requestData['projectId'])) {
                    $versionCollection = $service->getSprintReportVersions($requestData['projectId']);

                    $versionChoices = [];
                    foreach ($versionCollection->versions as $version) {
                        $versionChoices[$version->name] = $version->id;
                    }

                    // Override versionId with element with choices.
                    $form->add('versionId', ChoiceType::class, [
                        'placeholder' => 'sprint_report.select_an_option',
                        'choices' => $versionChoices,
                        'required' => true,
                        'label' => 'sprint_report.select_version',
                        'label_attr' => ['class' => 'label'],
                        'row_attr' => ['class' => 'form-choices'],
                        'attr' => [
                            'data-choices-target' => 'choices',
                            'data-sprint-report-target' => 'version',
                            'data-action' => 'sprint-report#submitForm',
                        ],
                    ]);
                }
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sprintReportFormData = $form->getData();

            $projectId = $form->get('projectId')->getData();
            $versionId = $form->get('versionId')->getData();
            $dataProvider = $form->get('dataProvider')->getData();

            if (!empty($projectId) && !empty($versionId) && !empty($dataProvider)) {
                $service = $this->dataProviderService->getService($dataProvider);

                $reportData = $service->getSprintReportData($projectId, $versionId);

                $budget = $this->sprintReportService->getBudget($projectId, $versionId);
            }
        }

        return $this->render('sprint_report/index.html.twig', [
            'form' => $form->createView(),
            'data' => $sprintReportFormData,
            'report' => $reportData,
            'budget' => $budget ?? null,
            'budgetEndpoint' => $this->generateUrl('app_sprint_report_budget', []),
        ]);
    }

    #[Route('/budget', name: 'app_sprint_report_budget', methods: ['POST'])]
    public function updateBudget(Request $request): Response
    {
        $data = $request->toArray();

        $projectId = $data['projectId'] ?? throw new HttpException(400, 'Missing projectId.');
        $versionId = $data['versionId'] ?? throw new HttpException(400, 'Missing versionId.');
        $budgetAmount = $data['budget'] ?? null;

        $budget = $this->sprintReportService->saveBudget($projectId, $versionId, $budgetAmount);

        return new JsonResponse([
            'projectId' => $budget->getProjectId(),
            'versionId' => $budget->getVersionId(),
            'budget' => $budget->getBudget(),
        ]);
    }

    /**
     * @throws MpdfException
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    #[Route('/generate-pdf', name: 'app_sprint_report_pdf', methods: ['GET'])]
    public function generatePdf(Request $request): void
    {
        $projectId = (string) $request->query->get('projectId');
        $versionId = (string) $request->query->get('versionId');
        $providerId = (int) $request->query->get('dataProviderId');

        $dataProvider = $this->dataProviderRepository->find($providerId);

        if (null != $dataProvider) {
            $service = $this->dataProviderService->getService($dataProvider);

            $reportData = $service->getSprintReportData($projectId, $versionId);

            $html = $this->renderView('sprint_report/pdf.html.twig', [
                'report' => $reportData,
                'data' => [
                    'projectId' => $projectId,
                    'versionId' => $versionId,
                ],
            ]);

            $mpdf = new Mpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output();
        } else {
            throw new EconomicsException('dataProviderId not set', 400);
        }
    }
}
