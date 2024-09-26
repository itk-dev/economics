<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Enum\SubscriptionSubjectEnum;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Psr\Log\LoggerInterface;
use Twig\Environment;

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
     * Handles a subscription.
     *
     * @param Subscription $subscription the subscription to handle
     * @param mixed $fromDate the start date of the report to generate
     * @param mixed $toDate the end date of the report to generate
     *
     * @throws MpdfException if there is an error with the PDF
     */
    public function handleSubscription(Subscription $subscription, $fromDate, $toDate): void
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
                $mpdf->Output('testhest'.$subscription->getId().'.pdf', \Mpdf\Output\Destination::FILE);
                break;
            default:
                break;
        }
    }
}
