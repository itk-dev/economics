<?php

namespace App\Controller;

use App\Form\SprintReportType;
use App\Model\SprintReport\SprintReportFormData;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Service\SprintReportService;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/sprint-report')]
#[IsGranted('ROLE_REPORT')]
class SprintReportController extends AbstractController
{
    public function __construct(
        private readonly SprintReportService $sprintReportService,
        private readonly ProjectRepository $projectRepository,
        private readonly VersionRepository $versionRepository,
    ) {
    }

    #[Route('/', name: 'app_sprint_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $sprintReportFormData = new SprintReportFormData();

        $requestData = $request->query->all();

        if (isset($requestData['sprint_report'])) {
            $requestData = $requestData['sprint_report'];
        }

        $selectedProject = null;

        if (!empty($requestData['project'])) {
            $selectedProject = $this->projectRepository->find($requestData['project']);
        }

        $form = $this->createForm(SprintReportType::class, $sprintReportFormData, [
            'action' => $this->generateUrl('app_sprint_report'),
            'method' => 'GET',
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
            'project' => $selectedProject,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sprintReportFormData = $form->getData();

            $project = $form->get('project')->getData();
            $version = $form->get('version')->getData();

            if (null !== $project && null !== $version) {
                $reportData = $this->sprintReportService->getSprintReportData($project, $version);

                $budget = $this->sprintReportService->getBudget($project->getId(), $version->getId());
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

        $project = $this->projectRepository->find($projectId);
        $version = $this->versionRepository->find($versionId);

        if (null == $project || null == $version) {
            throw new NotFoundHttpException('Project or version not found.');
        }

        $reportData = $this->sprintReportService->getSprintReportData($project, $version);

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
