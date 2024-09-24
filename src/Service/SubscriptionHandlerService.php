<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Enum\SubscriptionSubjectEnum;
use App\Exception\EconomicsException;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SubscriptionHandlerService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly VersionRepository $versionRepository,
        private readonly HourReportService $hourReportService,
        private readonly Environment $environment,
    ) {
    }

    /**
     * @throws MpdfException
     * @throws SyntaxError
     * @throws EconomicsException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function handleSubscription(Subscription $subscription, $fromDate, $toDate)
    {
        switch ($subscription->getSubject()) {
            case SubscriptionSubjectEnum::HOUR_REPORT:
                $hourReport = json_decode($subscription->getUrlParams())->hour_report;
                $projectId = $hourReport->project ?? null;
                $project = $this->projectRepository->find($projectId);
                if (!$project) {
                    exit('error');
                }
                $versionId = $hourReport->version ?? null;
                $version = null;
                if ($versionId) {
                    $version = $this->versionRepository->findOneBy($versionId);
                }
                $reportData = $this->hourReportService->getHourReport($project, $fromDate, $toDate, $version);

                $renderedReport = $this->environment->render('reports/hour_report.html.twig', [
                    'controller_name' => 'HourReportController',
                    'data' => $reportData,
                    'mode' => 'hour_report',
                ]);

                $mpdf = new Mpdf();
                $mpdf->WriteHTML($renderedReport);
                $mpdf->Output('testhest.pdf', \Mpdf\Output\Destination::FILE);
                return 'Hello World!';
                break;
        }
    }
}
