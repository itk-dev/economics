<?php

namespace App\Controller;

use App\Form\TeamReportDateIntervalType;
use App\Repository\WorklogRepository;
use App\Service\ViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/team-report')]
class TeamReportController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    #[Route('', name: 'app_team_reports_create')]
    public function create(Request $request, WorklogRepository $worklogRepository): Response
    {
        $worklogsSorted = $worklogRepository->findBy(
            [],
            ['started' => 'ASC']
        );

        $firstWorklog = reset($worklogsSorted);

        $form = $this->createForm(
            TeamReportDateIntervalType::class,
            [
                'firstLog' => $firstWorklog->getStarted(),
                'view' => $this->viewService->getCurrentViewId(),
            ],
            ['action' => $this->generateUrl('app_team_reports_output', $this->viewService->addView([])), 'method' => 'GET']
        );

        return $this->render('team-report/create.html.twig', $this->viewService->addView([
            'form' => $form,
        ]));
    }

    /**
     * @throws \Exception
     */
    #[Route('/output', name: 'app_team_reports_output')]
    public function output(Request $request): Response
    {
        $queryElements = $request->query->all();
        $dateInterval = $queryElements['team_report_date_interval'] ?? null;

        if (empty($dateInterval['dateFrom']) || empty($dateInterval['dateTo'])) {
            return $this->redirectToRoute('app_team_reports_create', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'team-report/output.html.twig',
            [
                'dateInterval' => $dateInterval,
                'view' => $dateInterval['view'],
                'currentQuery' => $request->query->all(),
            ]
        );
    }
}
