<?php

namespace App\Tests\Unit\Command;

use App\Command\SyncModifiedCommand;
use App\Service\LeantimeApiService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SyncModifiedCommandTest extends TestCase
{
    private LeantimeApiService $leantimeApiService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->leantimeApiService = $this->createMock(LeantimeApiService::class);

        $command = new SyncModifiedCommand($this->leantimeApiService);
        $this->commandTester = new CommandTester($command);
    }

    public function testDefaultIntervalUsesOneHour(): void
    {
        $this->leantimeApiService->expects($this->once())
            ->method('updateAll')
            ->with(
                true,
                $this->callback(function (\DateTime $date) {
                    $expected = (new \DateTime())->sub(new \DateInterval('PT1H'));

                    return abs($date->getTimestamp() - $expected->getTimestamp()) < 5;
                }),
            );

        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testCustomInterval(): void
    {
        $this->leantimeApiService->expects($this->once())
            ->method('updateAll')
            ->with(
                true,
                $this->callback(function (\DateTime $date) {
                    $expected = (new \DateTime())->sub(new \DateInterval('P1D'));

                    return abs($date->getTimestamp() - $expected->getTimestamp()) < 5;
                }),
            );

        $this->commandTester->execute(['--interval' => 'P1D']);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }
}
