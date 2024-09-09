<?php

namespace App\Controller;

use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/users', name: 'app_planning_users')]
    public function planningUsers(): Response
    {
        return $this->createResponse('users');
    }

    #[Route('/projects', name: 'app_planning_projects')]
    public function planningProjects(): Response
    {
        return $this->createResponse('projects');
    }

    private function createResponse(string $mode): Response
    {
        $planningData = $this->planningService->getPlanningData();

        return $this->render('planning/planning.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $planningData,
            'mode' => $mode,
        ]);
    }
}
