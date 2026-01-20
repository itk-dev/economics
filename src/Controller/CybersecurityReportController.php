<?php

namespace App\Controller;

use App\Form\CybersecurityReportType;
use App\Model\Reports\CybersecurityReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\CybersecurityReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/cybersecurity_report')]
#[IsGranted('ROLE_REPORT')]
final class CybersecurityReportController extends AbstractController
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ?string $defaultDataProvider,
        private readonly CybersecurityReportService $cybersecurityReportService,
    ) {
    }
    #[Route('/', name: 'app_cybersecurity_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $reportFormData = new CyberSecurityReportFormData();

        $dataProvider = null;
        $version = null;

        $requestData = $request->query->all('cybersecurity_report');

        if (!empty($requestData['dataProvider'])) {
            $dataProvider = $this->dataProviderRepository->find($requestData['dataProvider']);
        } elseif (null !== $this->defaultDataProvider) {
            $dataProvider = $this->dataProviderRepository->find($this->defaultDataProvider);
        }

        $form = $this->createForm(CybersecurityReportType::class, $reportFormData, [
            // Since this is only a filtering form, csrf is not needed.
            'csrf_protection' => false,
            'action' => $this->generateUrl('app_cybersecurity_report'),
            'method' => 'GET',
            'attr' => [
                'id' => 'cybersecurity_report',
            ],
            'data_provider' => $dataProvider,
            'version' => $version,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $version = $form->get('version')->getData() ?? null;
            $fromDate = $form->get('fromDate')->getData() ?? null;
            $toDate = $form->get('toDate')->getData() ?? null;

            if ($version && $fromDate && $toDate) {
                $reportData = $this->cybersecurityReportService->getCybersecurityReport($fromDate, $toDate, $version);
            }
        }

        return $this->render('reports/reports.html.twig', [
            'controller_name' => 'CybersecurityReportController',
            'form' => $form,
            'data' => $reportData,
            'mode' => 'cybersecurity_report',
        ]);
    }
}
