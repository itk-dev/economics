<?php

namespace App\Command;

use App\Entity\Subscription;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionHandlerService;
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
        private readonly SubscriptionHandlerService  $subscriptionHandlerService,
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

/*        $hest = new Subscription();
        $hest->setEmail('kjej@aarhus.dk');
        $hest->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        $hest->setFrequency(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $hest->setLastSent(new \DateTime());
        $this->subscriptionRepository->save($hest, true);*/


        $subscriptions = $this->subscriptionRepository->findAll();
        $now = new \DateTime();

        foreach ($subscriptions as $subscription) {
            $lastSent = $subscription->getLastSent();
            $interval = $lastSent->diff($now);
            switch ($subscription->getFrequency()) {
                case SubscriptionFrequencyEnum::FREQUENCY_MONTHLY:
                    if ($interval->m >= 1) {
                        $io->writeln('Sending monthly '.$subscription->getSubject()->value.' to '.$subscription->getEmail());
                        $this->subscriptionHandlerService->handleSubscription($subscription);
                    }
                    break;
                case SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY:
                    if ($interval->m >= 3) {
                        $io->writeln('Sending quarterly '.$subscription->getSubject()->value.' to '.$subscription->getEmail());
                        $this->subscriptionHandlerService->handleSubscription($subscription);
                    }
                    break;
            }
        }

        $io->writeln('Done! :D');

        return Command::SUCCESS;
    }
}
