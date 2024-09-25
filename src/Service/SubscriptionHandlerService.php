<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Enum\SubscriptionSubjectEnum;
use App\Exception\EconomicsException;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
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
                    $this->logger->error('Project was not found with ID='.$projectId);
                }
                $versionId = $hourReport->version ?? null;
                $version = null;
                if ($versionId) {
                    $version = $this->versionRepository->findOneBy($versionId);
                }
                $reportData = $this->hourReportService->getHourReport($project, $fromDate, $toDate, $version);

                $renderedReport = $this->environment->render('subscription/subscription_hour_report.html.twig', [
                    'controller_name' => 'HourReportController',
                    'data' => $reportData,
                    'mode' => 'hour_report',
                    'fromDate' => $fromDate->format('d-m-Y'),
                    'toDate' => $toDate->format('d-m-Y'),
                ]);

                $mpdf = new Mpdf();
                $mpdf->WriteHTML($renderedReport);
                $mpdf->Output('testhest.pdf', \Mpdf\Output\Destination::FILE);
                return 'Hello World!';
                break;
            default:

                break;
        }
    }
}
