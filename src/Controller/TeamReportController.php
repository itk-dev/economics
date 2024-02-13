<?php

namespace App\Controller;

use App\Form\TeamReportDateIntervalType;
use App\Repository\WorklogRepository;
use App\Service\TeamReportService;
use App\Service\ViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/team-report')]
class TeamReportController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService
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
    public function output(Request $request, WorklogRepository $worklogRepository): Response
    {
        $queryElements = $request->query->all();
        $dateInterval = $queryElements['team_report_date_interval'] ?? null;

        if (empty($dateInterval['dateFrom']) || empty($dateInterval['dateTo'])) {
            return $this->redirectToRoute('app_team_reports_create', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
        }

        $page = $queryElements['page'] ?? '1';

        $result = $worklogRepository->getTeamReportData(
            new \DateTime($dateInterval['dateFrom']),
            new \DateTime($dateInterval['dateTo'].' 23:59:59'),
            $queryElements['view'],
            $page
        );

        return $this->render(
            'team-report/output.html.twig',
            [
                'dateInterval' => $dateInterval,
                'view' => $queryElements['view'],
                'result' => $result,
                'currentQuery' => $queryElements,
            ]
        );
    }

    /**
     * @throws \Exception
     */
    #[Route('/output/export', name: 'app_team_reports_output_export', methods: ['GET'])]
    public function export(Request $request, WorklogRepository $worklogRepository, TeamReportService $teamReportService, EntityManagerInterface $entityManager): StreamedResponse|RedirectResponse
    {
        $queryElements = $request->query->all();
        $dateInterval = $queryElements['team_report_date_interval'] ?? null;

        if (empty($dateInterval['dateFrom']) || empty($dateInterval['dateTo'])) {
            return $this->redirectToRoute('app_team_reports_create', [], Response::HTTP_SEE_OTHER);
        }

        $response = new StreamedResponse(function () use ($teamReportService, $queryElements, $worklogRepository, $entityManager, $dateInterval) {
            $page = 1;
            $writer = $teamReportService->initWriter($queryElements['team_report_date_interval']);
            do {
                $data = $worklogRepository->getTeamReportData(
                    new \DateTime($dateInterval['dateFrom']),
                    new \DateTime($dateInterval['dateTo'].' 23:59:59'),
                    $queryElements['view'],
                    $page);
                $writer = $teamReportService->spreadsheetWriteData($data, $writer);
                $entityManager->clear();
                gc_collect_cycles();
                ++$page;
            } while (!empty($data->getItems()));

            $writer->close();
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel');

        return $response;
    }
}
