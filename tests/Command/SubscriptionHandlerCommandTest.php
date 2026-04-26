<?php

namespace App\Tests\Command;

use App\Command\SubscriptionHandlerCommand;
use App\Entity\Subscription;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionHandlerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SubscriptionHandlerCommandTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionHandlerService $subscriptionHandlerService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->subscriptionHandlerService = $this->createMock(SubscriptionHandlerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createCommandTester(): CommandTester
    {
        $command = new SubscriptionHandlerCommand(
            $this->subscriptionRepository,
            $this->subscriptionHandlerService,
            $this->logger,
        );

        return new CommandTester($command);
    }

    public function testMonthlySubscriptionDue(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getFrequency')->willReturn(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->method('getSubject')->willReturn(SubscriptionSubjectEnum::HOUR_REPORT);
        // Last sent 2 months ago
        $subscription->method('getLastSent')->willReturn(new \DateTime('-2 months'));

        $this->subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $this->subscriptionHandlerService->expects($this->once())
            ->method('handleSubscription');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testMonthlySubscriptionNotDueSkips(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getFrequency')->willReturn(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->method('getSubject')->willReturn(SubscriptionSubjectEnum::HOUR_REPORT);
        // Last sent today
        $subscription->method('getLastSent')->willReturn(new \DateTime());

        $this->subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $this->subscriptionHandlerService->expects($this->never())
            ->method('handleSubscription');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testQuarterlySubscriptionDue(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getFrequency')->willReturn(SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY);
        $subscription->method('getSubject')->willReturn(SubscriptionSubjectEnum::HOUR_REPORT);
        // Last sent 4 months ago
        $subscription->method('getLastSent')->willReturn(new \DateTime('-4 months'));

        $this->subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $this->subscriptionHandlerService->expects($this->once())
            ->method('handleSubscription');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testNoSubjectLogsError(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getFrequency')->willReturn(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->method('getSubject')->willReturn(null);
        $subscription->method('getLastSent')->willReturn(null);

        $this->subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $this->logger->expects($this->once())->method('error');
        $this->subscriptionHandlerService->expects($this->never())->method('handleSubscription');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testNullLastSentTreatedAsDue(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getFrequency')->willReturn(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->method('getSubject')->willReturn(SubscriptionSubjectEnum::HOUR_REPORT);
        // Never sent before
        $subscription->method('getLastSent')->willReturn(null);

        $this->subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $this->subscriptionHandlerService->expects($this->once())
            ->method('handleSubscription');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testNoSubscriptionsReturnsSuccess(): void
    {
        $this->subscriptionRepository->method('findAll')->willReturn([]);

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testHandlerExceptionIsCaughtAndLogged(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getFrequency')->willReturn(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->method('getSubject')->willReturn(SubscriptionSubjectEnum::HOUR_REPORT);
        $subscription->method('getLastSent')->willReturn(null);

        $this->subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $this->subscriptionHandlerService->method('handleSubscription')
            ->willThrowException(new \RuntimeException('Test error'));

        $this->logger->expects($this->once())->method('error');

        $tester = $this->createCommandTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
