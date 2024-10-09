<?php

namespace App\Controller;

use App\Form\ForecastReportType;
use App\Model\Reports\ForecastReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\ForecastReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/forecast_report')]
#[IsGranted('ROLE_REPORT')]
class ForecastReportController extends AbstractController
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ForecastReportService $forecastReportService,
    ) {
    }

    /**
     * @throws \Exception
     */
    #[Route('/', name: 'app_forecast_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $error = null;
        $mode = 'forecast_report';
        $reportFormData = new ForecastReportFormData();

        $form = $this->createForm(ForecastReportType::class, $reportFormData, [
            'action' => $this->generateUrl('app_forecast_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'sprint_report',
            ],
            'csrf_protection' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fromDate = $form->get('dateFrom')->getData();
            $toDate = $form->get('dateTo')->getData();

            $reportData = $this->forecastReportService->getForecastReport($fromDate, $toDate);
        }

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'ForecastReportController',
            'form' => $form,
            'error' => $error,
            'data' => $reportData,
            'mode' => $mode,
        ]);
    }
}
