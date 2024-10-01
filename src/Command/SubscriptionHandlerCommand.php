<?php

namespace App\Command;

use App\Entity\Subscription;
use App\Enum\SubscriptionFrequencyEnum;
use App\Exception\EconomicsException;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionHandlerService;
use Mpdf\MpdfException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsCommand(
    name: 'app:handle-subscriptions',
    description: 'Check if any notifications through subscriptions are due.',
)]
class SubscriptionHandlerCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionHandlerService $subscriptionHandlerService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input the input
     * @param OutputInterface $output the output
     *
     * @return int Command status
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $subscriptions = $this->subscriptionRepository->findAll();
        $dateNow = new \DateTime();
        foreach ($subscriptions as $subscription) {
            $subscriptionId = $subscription->getId();
            $lastSent = $subscription->getLastSent();
            $interval = $lastSent ? $lastSent->diff($dateNow) : new \DateInterval('P12M');
            $subject = $subscription->getSubject()->value ?? null;
            if (!$subject) {
                $this->logger->error('Subject was not found on subscription with ID='.$subscriptionId);
                continue;
            }

            if (SubscriptionFrequencyEnum::FREQUENCY_MONTHLY->name === $subscription->getFrequency()->name && $interval->m >= 1) {
                $this->handleMonthlyFrequency($subscription, $io);
            } elseif (SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY->name === $subscription->getFrequency()->name && $interval->m >= 3) {
                $this->handleQuarterlyFrequency($subscription, $io, $dateNow);
            }
        }
        $io->writeln('Done processing '.count($subscriptions).' subscriptions.');

        return Command::SUCCESS;
    }

    /**
     * Handle the monthly frequency for a subscription by sending the monthly email
     * for the previous month.
     *
     * @param Subscription $subscription the subscription to handle
     * @param SymfonyStyle $io the console output interface
     *
     * @return void
     */
    private function handleMonthlyFrequency(Subscription $subscription, SymfonyStyle $io): void
    {
        $fromDate = new \DateTime('first day of previous month');
        $toDate = new \DateTime('last day of previous month');
        $io->writeln('Sending monthly '.$subscription->getSubject()->name.' to '.$subscription->getEmail());
        $this->handleSubscriptionWithExceptionHandling($subscription, $fromDate, $toDate);
    }

    /**
     * Handles the quarterly frequency for the given subscription.
     *
     * @param Subscription $subscription the subscription to handle
     * @param SymfonyStyle $io the SymfonyStyle instance for console output
     * @param \DateTime $dateNow the current date
     *
     * @return void
     */
    private function handleQuarterlyFrequency(Subscription $subscription, SymfonyStyle $io, \DateTime $dateNow): void
    {
        ['fromDate' => $fromDate, 'toDate' => $toDate] = $this->getLastQuarter($dateNow);
        $io->writeln('Sending quarterly '.$subscription->getSubject()->name.' to '.$subscription->getEmail());
        $this->handleSubscriptionWithExceptionHandling($subscription, $fromDate, $toDate);
    }

    /**
     * Handle a subscription with exception handling.
     *
     * @param Subscription $subscription the subscription object
     * @param \DateTime $fromDate the start date of the subscription
     * @param \DateTime $toDate the end date of the subscription
     *
     * @return void
     */
    private function handleSubscriptionWithExceptionHandling(Subscription $subscription, \DateTime $fromDate, \DateTime $toDate): void
    {
        try {
            $this->subscriptionHandlerService->handleSubscription($subscription, $fromDate, $toDate);
        } catch (MpdfException|EconomicsException|TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError|\Exception $e) {
            $this->logger->error('Subscription id: '.$subscription->getId().' - An exception occurred: ', ['exception' => $e]);
        }
    }

    /**
     * Get the start and end dates of the last quarter based on the given date.
     *
     * @param \DateTime $dateNow the current date
     *
     * @return array an array containing the start and end dates of the last quarter
     */
    private function getLastQuarter(\DateTime $dateNow): array
    {
        // Get current month
        $currentMonth = (int) $dateNow->format('m');
        $currentYear = (int) $dateNow->format('Y');

        // Define previous quarter based on current month
        if ($currentMonth <= 3) {
            $quarterStartMonth = 10;
            $quarterEndMonth = 12;
            $yearAdjustment = -1;
        } elseif ($currentMonth <= 6) {
            $quarterStartMonth = 1;
            $quarterEndMonth = 3;
            $yearAdjustment = 0;
        } elseif ($currentMonth <= 9) {
            $quarterStartMonth = 4;
            $quarterEndMonth = 6;
            $yearAdjustment = 0;
        } else {
            $quarterStartMonth = 7;
            $quarterEndMonth = 9;
            $yearAdjustment = 0;
        }

        // adjust year if previous quarter was last year
        $year = $currentYear + $yearAdjustment;

        $fromDate = new \DateTime("$year-$quarterStartMonth-01");
        $toDate = new \DateTime("$year-$quarterEndMonth-01");
        $toDate->modify('last day of this month');

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ];
    }
}
