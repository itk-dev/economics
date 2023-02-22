<?php

namespace App\Controller\SprintReport;

use App\Form\SprintReport\SprintReportType;
use App\Model\SprintReport\SprintReportFormData;
use App\Service\ProjectTracker\ApiServiceInterface;
use App\Service\SprintReport\SprintReportService;
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
    public function __construct(
        private readonly ApiServiceInterface $apiService,
        private readonly SprintReportService $sprintReportService,
    ) {
    }

    #[Route('/', name: 'app_sprint_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $sprintReportFormData = new SprintReportFormData();

        $form = $this->createForm(SprintReportType::class, $sprintReportFormData);

        $projects = $this->apiService->getAllProjects();

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

        $requestData = $request->request->all();

        if (isset($requestData['sprint_report'])) {
            $requestData = $requestData['sprint_report'];
        }

        if (!empty($requestData['projectId'])) {
            $project = $this->apiService->getProject($requestData['projectId']);

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
                $reportData = $this->apiService->getSprintReportData($projectId, $versionId);

                $budget = $this->sprintReportService->getBudget($projectId, $versionId);
            }
        }

        return $this->render('sprint_report/index.html.twig', [
            'form' => $form->createView(),
            'data' => $sprintReportFormData,
            'report' => $reportData,
            'budget' => $budget ?? null,
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
    public function generatePdf(Request $request)
    {
        $projectId = (string) $request->query->get('projectId');
        $versionId = (string) $request->query->get('versionId');

        $reportData = $this->apiService->getSprintReportData($projectId, $versionId);

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
