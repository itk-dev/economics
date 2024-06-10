<?php

namespace App\Controller;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\PlanningType;
use App\Model\Planning\PlanningFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use App\Service\ViewService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ViewService $viewService,
        private readonly ?string $defaultDataProvider,
    ) {
    }

    #[Route('/', name: 'app_planning')]
    public function index(Request $request): Response
    {
        return $this->redirectToRoute('app_planning_users');
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    #[Route('/users', name: 'app_planning_users')]
    public function planningUsers(Request $request): Response
    {
        return $this->createResponse($request, 'users');
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    #[Route('/projects', name: 'app_planning_projects')]
    public function planningProjects(Request $request): Response
    {
        return $this->createResponse($request, 'projects');
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    private function createResponse(Request $request, string $mode): Response
    {
        $planningFormData = new PlanningFormData();
        $form = $this->createForm(PlanningType::class, $planningFormData);

        $dataProviders = $this->dataProviderRepository->findAll();
        $defaultProvider = $this->dataProviderRepository->find($this->defaultDataProvider);

        if (null === $defaultProvider && count($dataProviders) > 0) {
            $defaultProvider = $dataProviders[0];
        }

        $form->add('dataProvider', EntityType::class, [
            'class' => DataProvider::class,
            'required' => true,
            'label' => 'planning.data_provider',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'class' => 'form-element',
            ],
            'help' => 'planning.data_provider_helptext',
            'data' => $this->dataProviderRepository->find($this->defaultDataProvider),
            'choices' => $dataProviders,
        ]);

        $form->handleRequest($request);

        $service = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $service = $this->dataProviderService->getService($planningFormData->dataProvider);
        } elseif (null !== $defaultProvider) {
            $service = $this->dataProviderService->getService($defaultProvider);
        }

        try {
            $planningData = $service?->getPlanningDataWeeks();
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $planningData = null;
        }

        return $this->render('planning/planning.html.twig', $this->viewService->addView([
            'controller_name' => 'PlanningController',
            'planningData' => $planningData,
            'error' => $error ?? null,
            'form' => $form,
            'mode' => $mode,
        ]));
    }
}
