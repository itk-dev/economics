<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Subscription;
use App\Entity\Version;
use App\Enum\SubscriptionSubjectEnum;
use App\Exception\EconomicsException;
use App\Model\Reports\HourReportData;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SubscriptionHandlerService
{
    public function __construct(
        private readonly string $emailFromAddress,
        private readonly ProjectRepository $projectRepository,
        private readonly VersionRepository $versionRepository,
        private readonly HourReportService $hourReportService,
        private readonly Environment $environment,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Send a notification email.
     *
     * @param array $notification the notification data
     *
     * @return void
     *
     * @throws TransportExceptionInterface
     */
    private function sendNotification(array $notification): void
    {
        $email = (new TemplatedEmail())
            ->from($notification['from'])
            ->to(new Address($notification['to']))
            ->subject($notification['subject'])
            ->htmlTemplate($notification['template'])
            ->attach($notification['fileAttachments']['file'], $notification['fileAttachments']['name'], 'application/pdf')
            ->context([
                'renderedReport' => $notification['data']['renderedReport'],
            ]);

        $this->mailer->send($email);
    }

    /**
     * Handles a subscription for hour report.
     *
     * @param Subscription $subscription the subscription object
     * @param \DateTime $fromDate the starting date of the report
     * @param \DateTime $toDate the ending date of the report
     *
     * @return void
     *
     * @throws EconomicsException
     * @throws LoaderError
     * @throws MpdfException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws \Exception
     */
    public function handleSubscription(Subscription $subscription, \DateTime $fromDate, \DateTime $toDate): void
    {
        if (SubscriptionSubjectEnum::HOUR_REPORT !== $subscription->getSubject()) {
            return;
        }

        $email = $subscription->getEmail() ?? '';

        $params = json_decode($subscription->getUrlParams() ?? '');
        $projectId = $params->hour_report->project ?? null;

        $project = $this->getProject($projectId);
        if (!$project) {
            return;
        }

        $version = $this->getVersion($params->hour_report->version ?? null);
        $reportData = $this->hourReportService->getHourReport($project, $fromDate, $toDate, $version);
        $mailData = $this->prepareMailData($subscription, $fromDate, $toDate, $project, $reportData, $email);

        $this->sendNotification($mailData);
    }

    /**
     * Get a project object by its ID.
     *
     * @param ?int $projectId the ID of the project, nullable
     *
     * @return ?Project the project object, or null if not found
     */
    private function getProject(?int $projectId): ?Project
    {
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            $this->logger->error('Project was not found with ID='.$projectId);
        }

        return $project;
    }

    /**
     * Get a version object by id.
     *
     * @param int|null $versionId the id of the version
     *
     * @return Version|null the version object, or null if not found
     */
    private function getVersion(?int $versionId): ?Version
    {
        return $versionId ? $this->versionRepository->findOneBy(['versionId' => $versionId]) : null;
    }

    /**
     * Prepare mail data for sending a subscription hour report.
     *
     * @param Subscription $subscription the subscription object
     * @param \DateTime $fromDate the starting date of the report
     * @param \DateTime $toDate the ending date of the report
     * @param Project $project the project object
     * @param HourReportData $reportData the report data
     * @param string $email the recipient email address
     *
     * @return array the prepared mail data
     *
     * @throws LoaderError
     * @throws MpdfException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \Exception
     */
    private function prepareMailData(Subscription $subscription, \DateTime $fromDate, \DateTime $toDate, Project $project, HourReportData $reportData, string $email): array
    {
        $renderedReport = $this->environment->render('subscription/subscription_hour_report.html.twig', [
            'controller_name' => 'HourReportController',
            'data' => $reportData,
            'mode' => 'hour_report',
            'projectName' => $project->getName(),
            'fromDate' => $fromDate->format('d-m-Y'),
            'toDate' => $toDate->format('d-m-Y'),
        ]);

        $attachment = $this->createPdfAttachment($renderedReport);
        $subject = $this->createSubject($subscription, $project);

        return [
            'from' => $this->emailFromAddress,
            'to' => $email,
            'subject' => $subject,
            'template' => 'email/email-subscription-hour_report.html.twig',
            'adminNotification' => false,
            'data' => [
                'renderedReport' => $renderedReport,
            ],
            'fileAttachments' => [
                'name' => $this->createAttachmentName($subscription, $fromDate, $toDate, $project),
                'file' => $attachment,
            ],
        ];
    }

    /**
     * Creates a PDF attachment from the given content.
     *
     * @param string $content the content of the PDF
     *
     * @return string the PDF attachment
     *
     * @throws MpdfException if there is an error with the PDF generation
     */
    private function createPdfAttachment(string $content): string
    {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML($content);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * Creates the subject for a subscription email.
     *
     * @param Subscription $subscription the subscription object
     * @param Project $project the project object
     *
     * @return string the subject string for the subscription email
     * @throws \Exception
     */
    private function createSubject(Subscription $subscription, Project $project): string
    {
        $subject = $subscription->getSubject();
        $frequency = $subscription->getFrequency();

        if (!$subject || !$frequency) {
            throw new \Exception('Subject or frequency is missing from the subscription.');
        }

        $subjectValue = $subject->value;
        $frequencyValue = $frequency->value;

        $subjectTranslation = $this->translator->trans('subscription.subjects.'.$subjectValue);
        $frequencyTranslation = $this->translator->trans('subscription.frequencies.'.$frequencyValue);

        if (empty($subjectTranslation) || empty($frequencyTranslation)) {
            throw new \Exception('Translation for subject or frequency is missing.');
        }

        return $subjectTranslation
            .' - '.$project->getName()
            .' - '.$frequencyTranslation;
    }

    /**
     * Creates the name for the attachment file.
     *
     * @param Subscription $subscription the subscription object
     * @param \DateTime $fromDate the starting date for the subscription period
     * @param \DateTime $toDate the ending date for the subscription period
     * @param Project $project the project object
     *
     * @return string the name of the attachment file
     * @throws \Exception
     */
    private function createAttachmentName(Subscription $subscription, \DateTime $fromDate, \DateTime $toDate, Project $project): string
    {
        $subject = $subscription->getSubject();

        if (!$subject) {
            throw new \Exception('Subject is missing from the subscription.');
        }

        $subjectValue = $subject->value;

        return $subjectValue.'_'.$project->getName().'_'.$fromDate->format('d-m-Y').'-'.$toDate->format('d-m-Y').'.pdf';
    }
}
