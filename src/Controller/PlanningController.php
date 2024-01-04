<?php

namespace App\Controller;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\PlanningType;
use App\Model\Planning\PlanningFormData;
use App\Repository\DataProviderRepository;
use App\Service\DataProviderService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    #[Route('/', name: 'app_planning')]
    public function index(Request $request): Response
    {
        $planningFormData = new PlanningFormData();

        $form = $this->createForm(PlanningType::class, $planningFormData);

        $form->add('dataProvider', EntityType::class, [
            'class' => DataProvider::class,
            'required' => true,
            'label' => 'planning.data_provider',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row'],
            'attr' => [
                'class' => 'form-element',
            ],
            'help' => 'planning.data_provider_helptext',
            'choices' => $this->dataProviderRepository->findAll(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service = $this->dataProviderService->getService($planningFormData->dataProvider);

            $planningData = $service->getPlanningData();
        }

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'planningData' => $planningData ?? null,
            'form' => $form,
        ]);
    }
}
