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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ViewService $viewService,
        private readonly ?int $planningDefaultDataProvider,
    ) {
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    #[Route('/', name: 'app_planning')]
    public function index(Request $request): Response
    {
        $planningFormData = new PlanningFormData();
        $form = $this->createForm(PlanningType::class, $planningFormData);

        $dataProviders = $this->dataProviderRepository->findAll();
        $defaultProvider = $this->dataProviderRepository->find($this->planningDefaultDataProvider);

        $form->add('dataProvider', EntityType::class, [
            'class' => DataProvider::class,
            'required' => true,
            'label' => 'planning.data_provider',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'class' => 'form-element',
            ],
            'help' => 'planning.data_provider_helptext',
            'data' => $this->dataProviderRepository->find($this->planningDefaultDataProvider),
            'choices' => $dataProviders,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service = $this->dataProviderService->getService($planningFormData->dataProvider);
        } else {
            $service = $this->dataProviderService->getService($defaultProvider);
        }

        $planningData = $service->getPlanningDataWeeks();

        return $this->render('planning/index.html.twig', $this->viewService->addView([
            'controller_name' => 'PlanningController',
            'planningData' => $planningData,
            'form' => $form,
        ]));
    }
}
