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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly TranslatorInterface $translator,
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

        $form->add('viewType', ChoiceType::class, [
            'required' => true,
            'label' => 'planning.view_type',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-element-wrapper'],
            'attr' => [
                'class' => 'form-element',
            ],
            'help' => 'planning.data_provider_helptext',
            'choices' => $this->getTypeChoices(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service = $this->dataProviderService->getService($planningFormData->dataProvider);
            $viewType = $form->getData()->viewType;
            switch ($viewType) {
                case 'week':
                    $planningData = $service->getPlanningDataWeeks();

                    return $this->render('planning/planning-weeks.html.twig', [
                        'controller_name' => 'PlanningController',
                        'planningData' => $planningData,
                    ]);
                case 'sprint':
                    $planningData = $service->getPlanningDataSprints();

                    return $this->render('planning/planning-sprints.html.twig', [
                        'controller_name' => 'PlanningController',
                        'planningData' => $planningData,
                    ]);
                default:
                    $planningData = $service->getPlanningDataSprints();
            }
        }

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'form' => $form,
        ]);
    }

    private function getTypeChoices()
    {
        return [
            $this->translator->trans('planning.week_view') => 'week',
            $this->translator->trans('planning.sprint_view') => 'sprint',
        ];
    }
}
