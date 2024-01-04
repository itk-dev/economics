<?php

namespace App\Controller;

use App\Exception\UnsupportedDataProviderException;
use App\Form\SprintReportType;
use App\Interface\DataProviderServiceInterface;
use App\Model\SprintReport\SprintReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use App\Service\SprintReportService;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/sprint-report')]
class SprintReportController extends AbstractController
{
    private DataProviderServiceInterface $service;

    /**
     * @throws UnsupportedDataProviderException
     */
    public function __construct(
        private readonly SprintReportService $sprintReportService,
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
        // TODO: Data provider should be selectable.
        $dataProvider = $this->dataProviderRepository->find(1);
        $this->service = $this->dataProviderService->getService($dataProvider);
    }

    #[Route('/', name: 'app_sprint_report')]
    public function index(Request $request): Response
    {

        $reportData = null;
        $sprintReportFormData = new SprintReportFormData();

        $form = $this->createForm(SprintReportType::class, $sprintReportFormData, [
            'action' => $this->generateUrl('app_sprint_report'),
            'method' => 'GET',
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
        ]);

        $projects = $this->service->getAllProjects();

        $projectChoices = [];

        foreach ($projects as $project) {
            $projectChoices[$project->name] = $project->key;
        }

        // Override projectId with element with choices.
        $form->add('projectId', ChoiceType::class, [
            'placeholder' => 'sprint_report.select_an_option',
            'choices' => $projectChoices,
            'required' => true,
            'label' => 'sprint_report.select_project',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'data-sprint-report-target' => 'project',
                'data-action' => 'sprint-report#submitFormProjectId',
            ],
        ]);

        $requestData = $request->query->all();

        if (isset($requestData['sprint_report'])) {
            $requestData = $requestData['sprint_report'];
        }

        if (!empty($requestData['projectId'])) {
            $project = $this->service->getProject($requestData['projectId']);

            $versionChoices = [];
            foreach ($project->versions as $version) {
                $versionChoices[$version->name] = $version->id;
            }

            // Override versionId with element with choices.
            $form->add('versionId', ChoiceType::class, [
                'placeholder' => 'sprint_report.select_an_option',
                'choices' => $versionChoices,
                'required' => true,
                'label' => 'sprint_report.select_version',
                'label_attr' => ['class' => 'label'],
                'attr' => [
                    'data-sprint-report-target' => 'version',
                    'data-action' => 'sprint-report#submitForm',
                ],
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sprintReportFormData = $form->getData();

            $projectId = $form->get('projectId')->getData();
            $versionId = $form->get('versionId')->getData();

            if (!empty($projectId) && !empty($versionId)) {
                $reportData = $this->service->getSprintReportData($projectId, $versionId);

                $budget = $this->sprintReportService->getBudget($projectId, $versionId);
            }
        }

        return $this->render('sprint_report/index.html.twig', [
            'form' => $form->createView(),
            'data' => $sprintReportFormData,
            'report' => $reportData,
            'budget' => $budget ?? null,
            'budgetEndpoint' => $this->generateUrl('app_sprint_report_budget'),
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
     */
    #[Route('/generate-pdf', name: 'app_sprint_report_pdf', methods: ['GET'])]
    public function generatePdf(Request $request): void
    {
        $projectId = (string) $request->query->get('projectId');
        $versionId = (string) $request->query->get('versionId');

        $reportData = $this->service->getSprintReportData($projectId, $versionId);

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
    }
}
