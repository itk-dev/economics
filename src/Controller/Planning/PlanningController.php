<?php

namespace App\Controller\Planning;

use App\Service\ProjectTracker\ApiServiceInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlanningController extends AbstractController
{
    public function __construct(
        private readonly ApiServiceInterface $apiService
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('/planning', name: 'app_planning')]
    public function index(): Response
    {
        $planningData = $this->apiService->getPlanningData();

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $planningData
        ]);
    }
}
