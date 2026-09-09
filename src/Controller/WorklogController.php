<?php

namespace App\Controller;

use App\Form\WorklogFilterType;
use App\Model\Invoices\WorklogFilterData;
use App\Repository\WorklogRepository;
use App\Service\WorklogExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/worklog', name: 'app_worklog_')]
#[IsGranted('ROLE_ADMIN')]
class WorklogController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, WorklogRepository $worklogRepository): Response
    {
        $filterData = new WorklogFilterData();
        $form = $this->createForm(WorklogFilterType::class, $filterData);
        $form->handleRequest($request);

        // The export link replays the active filter. Drop the page: it covers every page, so
        // carrying a page number would only mislead.
        $currentQuery = $request->query->all();
        unset($currentQuery['page']);

        return $this->render('worklog/index.html.twig', [
            'worklogs' => $worklogRepository->getFilteredPagination($filterData, $request->query->getInt('page', 1)),
            'form' => $form,
            'currentQuery' => $currentQuery,
        ]);
    }

    /**
     * Downloads every worklog matching the filter, across all pages, as CSV.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, WorklogExportService $worklogExportService): Response
    {
        // Rebinding the same form to the same query string is what keeps the export's filter
        // identical to the list's. It works because the form is GET with csrf_protection off.
        $filterData = new WorklogFilterData();
        $form = $this->createForm(WorklogFilterType::class, $filterData);
        $form->handleRequest($request);

        return $worklogExportService->generateCsvResponse($filterData);
    }
}
