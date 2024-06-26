<?php

namespace App\Controller;

use App\Exception\EconomicsException;
use App\Form\HourReportType;
use App\Model\Reports\HourReportFormData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Service\HourReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports/hour_report')]
class HourReportController extends AbstractController
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly HourReportService $hourReportService,
        private readonly ?string $defaultDataProvider,
        private readonly ProjectRepository $projectRepository,
        private readonly VersionRepository $versionRepository,
    ) {
    }

    /**
     * @throws EconomicsException
     * @throws \Exception
     */
    #[Route('/', name: 'app_hour_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $reportFormData = new HourReportFormData();

        $dataProvider = null;
        $project = null;
        $version = null;

        $requestData = $request->query->all('hour_report');

        if (!empty($requestData['dataProvider'])) {
            $dataProvider = $this->dataProviderRepository->find($requestData['dataProvider']);
        } elseif (null !== $this->defaultDataProvider) {
            $dataProvider = $this->dataProviderRepository->find($this->defaultDataProvider);
        }

        if (!empty($requestData['project'])) {
            $project = $this->projectRepository->find($requestData['project']);
        }

        if (!empty($requestData['version'])) {
            $version = $this->versionRepository->find($requestData['version']);
        }

        $form = $this->createForm(HourReportType::class, $reportFormData, [
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
            'action' => $this->generateUrl('app_hour_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'hour_report',
            ],
            'data_provider' => $dataProvider,
            'project' => $project,
            'version' => $version,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project = $form->get('project')->getData() ?? null;
            $version = $form->get('version')->getData() ?? null;
            $fromDate = $form->get('fromDate')->getData() ?? null;
            $toDate = $form->get('toDate')->getData() ?? null;

            if (null !== $project) {
                $reportData = $this->hourReportService->getHourReport($project, $fromDate, $toDate, $version);
            }
        }

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'HourReportController',
            'form' => $form,
            'data' => $reportData,
            'mode' => 'hour_report',
        ]);
    }
}
