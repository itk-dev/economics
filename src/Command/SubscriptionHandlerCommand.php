<?php

namespace App\Command;

use App\Enum\SubscriptionFrequencyEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionHandlerService;
use Mpdf\MpdfException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $subscriptions = $this->subscriptionRepository->findAll();
        $dateNow = new \DateTime();

        foreach ($subscriptions as $subscription) {
            $subscriptionId = $subscription->getId();
            $lastSent = $subscription->getLastSent();
            /* If $lastSent is undefined, we should assume that this is the first run since subscribing
            and set interval to always be true when checking below */
            $interval = $lastSent ? $lastSent->diff($dateNow) : new \DateInterval('P12M');
            $subject = $subscription->getSubject()->value ?? null;
            if (!$subject) {
                $this->logger->error('Subject was not found on subscription with ID='.$subscription->getId());
                continue;
            }
            switch ($subscription->getFrequency()) {
                case SubscriptionFrequencyEnum::FREQUENCY_MONTHLY:
                    if ($interval->m >= 1) {
                        $fromDate = new \DateTime('first day of previous month');
                        $toDate = new \DateTime('last day of previous month');
                        $io->writeln('Sending monthly '.$subject.' to '.$subscription->getEmail());
                        try {
                            $this->subscriptionHandlerService->handleSubscription($subscription, $fromDate, $toDate);
                        } catch (MpdfException $e) {
                            $this->logger->error('Subscription id: '.$subscriptionId.' - An MpdfException occurred: ', ['exception' => $e]);
                        }
                    }
                    break;
                case SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY:
                    if ($interval->m >= 3) {
                        ['fromDate' => $fromDate, 'toDate' => $toDate] = $this->getLastQuarter($dateNow);
                        $io->writeln('Sending quarterly '.$subject.' to '.$subscription->getEmail());
                        try {
                            $this->subscriptionHandlerService->handleSubscription($subscription, $fromDate, $toDate);
                        } catch (MpdfException $e) {
                            $this->logger->error('Subscription id: '.$subscriptionId.' - An MpdfException occurred: ', ['exception' => $e]);
                        }
                    }
                    break;
            }
        }

        $io->writeln('Done processing '.count($subscriptions).' subscriptions.');

        return Command::SUCCESS;
    }

    /**
     * Get the start and end dates of the last quarter based on the given date.
     *
     * @param \Datetime $dateNow the current date
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
