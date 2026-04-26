<?php

namespace App\Tests\Command;

use App\Entity\Project;
use App\Service\LeantimeApiService;
use App\Command\SyncCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SyncCommandTest extends TestCase
{
    private LeantimeApiService $leantimeApiService;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->leantimeApiService = $this->createMock(LeantimeApiService::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $command = new SyncCommand(
            $this->leantimeApiService,
            '',
            $this->httpClient,
            $this->logger,
        );

        $this->commandTester = new CommandTester($command);
    }

    public function testAllOptionCallsUpdateAll(): void
    {
        $this->leantimeApiService->expects($this->once())
            ->method('updateAll')
            ->with(false, null, false);

        $this->commandTester->execute(['--all' => true]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testAllWithJobOption(): void
    {
        $this->leantimeApiService->expects($this->once())
            ->method('updateAll')
            ->with(true, null, false);

        $this->commandTester->execute(['--all' => true, '--job' => true]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testProjectsOptionCallsUpdateWithProjectClass(): void
    {
        $this->leantimeApiService->expects($this->once())
            ->method('update')
            ->with(Project::class, false, null, false);

        $this->commandTester->execute(['--projects' => true]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testInvalidModifiedOptionReturnsFailure(): void
    {
        $this->commandTester->execute(['--modified' => 'not-a-valid-date']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Error parsing modified option', $this->commandTester->getDisplay());
    }

    public function testNoOptionsReturnsSuccess(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testMonitoringUrlCalledWhenSet(): void
    {
        $command = new SyncCommand(
            $this->leantimeApiService,
            'https://monitoring.example.com/ping',
            $this->httpClient,
            $this->logger,
        );

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://monitoring.example.com/ping');

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
