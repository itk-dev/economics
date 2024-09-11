<?php

namespace App\Controller;

use App\Form\PlanningType;
use App\Model\Planning\PlanningFormData;
use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * @throws \Exception
     */
    private function preparePlaningData(Request $request): array
    {
        $planningFormData = new PlanningFormData();
        $planningFormData->year = (new \DateTime())->format('Y');
        $form = $this->createForm(PlanningType::class, $planningFormData, [
            'action' => $this->generateUrl('app_planning'),
            'attr' => [
                'id' => 'sprint_report',
            ],
            'years' => [(new \DateTime())->format('Y'), (new \DateTime())->modify('+1 year')->format('Y')],
            'method' => 'GET',
            'csrf_protection' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $planningFormData = $form->getData();
        }

        $planningData = $this->planningService->getPlanningData($planningFormData->year);

        return ['planningData' => $planningData, 'form' => $form, 'year' => $planningFormData->year];
    }

    #[Route('/', name: 'app_planning')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_planning_users');
    }

    /**
     * @throws \Exception
     */
    #[Route('/users', name: 'app_planning_users')]
    public function planningUsers(Request $request): Response
    {
        $data = $this->preparePlaningData($request);

        return $this->createResponse('users', $data);
    }

    /**
     * @throws \Exception
     */
    #[Route('/projects', name: 'app_planning_projects')]
    public function planningProjects(Request $request): Response
    {
        $data = $this->preparePlaningData($request);

        return $this->createResponse('projects', $data);
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
}
