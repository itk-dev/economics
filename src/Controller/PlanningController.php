<?php

namespace App\Controller;

use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
    }

    #[Route('/', name: 'app_planning')]
    public function index(): Response
    {
        // TODO: Data provider should be selectable.
        $dataProvider = $this->dataProviderRepository->find(1);

        if (null != $dataProvider) {
            $service = $this->dataProviderService->getService($dataProvider);

            $planningData = $service->getPlanningData();
        }

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $planningData ?? null,
        ]);
    }
}
