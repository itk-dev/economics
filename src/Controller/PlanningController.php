<?php

namespace App\Controller;

use App\Service\ProjectTrackerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly ProjectTrackerInterface $projectTracker
    ) {
    }

    #[Route('/', name: 'app_planning')]
    public function index(): Response
    {
        $planningData = $this->projectTracker->getPlanningData();

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $planningData,
        ]);
    }
}
