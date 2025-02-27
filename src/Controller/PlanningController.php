<?php

namespace App\Controller;

use App\Form\PlanningType;
use App\Model\Planning\PlanningFormData;
use App\Repository\ProjectRepository;
use App\Service\DataSynchronizationService;
use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/planning')]
#[IsGranted('ROLE_PLANNING')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly PlanningService $planningService,
    ) {
    }

    #[Route('/', name: 'app_planning')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_planning_users');
    }

    #[Route('/list/projects', name: 'app_planning_projects_list')]
    public function projectsList(ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->getIncluded()->getQuery()->toIterable();

        $res = [];

        foreach ($projects as $project) {
            $res[] = (object) [
                'label' => $project->getName(),
                'value' => $project->getId(),
            ];
        }

        return new JsonResponse($res);
    }

    #[Route('/users', name: 'app_planning_users')]
    public function planningUsers(Request $request): Response
    {
        $data = $this->preparePlanningData($request);

        return $this->createResponse('users', $data);
    }

    #[Route('/projects', name: 'app_planning_projects')]
    public function planningProjects(Request $request): Response
    {
        $data = $this->preparePlanningData($request);

        return $this->createResponse('projects', $data);
    }

    #[Route('/holiday', name: 'app_planning_holiday')]
    public function holidayPlanning(Request $request): Response
    {
        $data = $this->preparePlanningData($request, true);

        return $this->createResponse('holiday', $data);
    }

    #[Route('/sync-issues', name: 'app_planning_issues_sync', methods: ['POST'])]
    public function syncAllIssues(Request $request, DataSynchronizationService $dataSynchronizationService, ProjectRepository $projectRepository): Response
    {
        $projectId = $request->query->get('id');

        if (null === $projectId) {
            throw new BadRequestHttpException('Project query parameter "id" not set');
        }

        $project = $projectRepository->find($projectId);

        if (null === $project) {
            throw new BadRequestHttpException('Project not found');
        }

        $dataProvider = $project->getDataProvider();
        if (null === $dataProvider) {
            throw new BadRequestHttpException('Project data provider not set');
        }

        $issuesSynced = 0;
        $dataSynchronizationService->syncIssuesForProject((int)$projectId, $dataProvider, function () use (&$issuesSynced) {
            ++$issuesSynced;
        });

        return new JsonResponse(['issuesSynced' => $issuesSynced], 200);
    }

    private function createResponse(string $mode, array $data): Response
    {
        return $this->render('planning/planning.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $data['planningData'],
            'form' => $data['form'],
            'year' => $data['year'],
            'mode' => $mode,
        ]);
    }

    /**
     * @throws \Exception
     */
    private function preparePlanningData(Request $request, bool $holidayPlanning = false): array
    {
        $planningFormData = new PlanningFormData();
        $planningFormData->year = (int) (new \DateTime())->format('Y');
        $form = $this->createForm(PlanningType::class, $planningFormData, [
            'action' => $this->generateUrl($request->attributes->get('_route')),
            'attr' => [
                'id' => 'report',
            ],
            'years' => [
                (new \DateTime())->modify('-1 year')->format('Y'),
                (new \DateTime())->format('Y'),
                (new \DateTime())->modify('+1 year')->format('Y'),
            ],
            'method' => 'GET',
            'csrf_protection' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $planningFormData = $form->getData();
        }

        $planningData = $this->planningService->getPlanningData($planningFormData->year, $planningFormData->group ?? null, $holidayPlanning);

        return ['planningData' => $planningData, 'form' => $form, 'year' => $planningFormData->year];
    }
}
