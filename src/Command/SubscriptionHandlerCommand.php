<?php

namespace App\Command;

use App\Enum\SubscriptionFrequencyEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionHandlerService;
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
        $now = new \DateTime();

        foreach ($subscriptions as $subscription) {
            $lastSent = $subscription->getLastSent();
            /* If $lastSent is undefined, we should assume that this is the first run since subscribing
            and set interval to always be true when checking below */
            $interval = $lastSent ? $lastSent->diff($now) : new \DateInterval('P12M');
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
                        $message = $this->subscriptionHandlerService->handleSubscription($subscription, $fromDate, $toDate);
                        $io->writeln($message);
                    }
                    break;
                case SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY:
                    if ($interval->m >= 3) {
                        $io->writeln('Sending quarterly '.$subject.' to '.$subscription->getEmail());
                        $this->subscriptionHandlerService->handleSubscription($subscription);
                    }
                    break;
            }
        }

        $io->writeln('Done! :D');

        return Command::SUCCESS;
    }
}
