<?php

namespace App\Controller;

use App\Exception\UnsupportedDataProviderException;
use App\Interface\DataProviderServiceInterface;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    private DataProviderServiceInterface $service;

    /**
     * @throws UnsupportedDataProviderException
     */
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
        // TODO: Data provider should be selectable.
        $dataProvider = $this->dataProviderRepository->find(1);
        $this->service = $this->dataProviderService->getService($dataProvider);
    }

    #[Route('/', name: 'app_planning')]
    public function index(): Response
    {
        $planningData = $this->service->getPlanningData();

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $planningData,
        ]);
    }
}
