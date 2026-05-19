<?php

namespace App\Tests\Unit\Command;

use App\Command\DataProviderListCommand;
use App\Entity\DataProvider;
use App\Repository\DataProviderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DataProviderListCommandTest extends TestCase
{
    public function testExecuteDisplaysTable(): void
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getId')->willReturn(1);
        $provider->method('getName')->willReturn('Leantime');
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('getClass')->willReturn('LeantimeApiService');
        $provider->method('getUrl')->willReturn('https://leantime.example.com');
        $provider->method('isEnableClientSync')->willReturn(false);
        $provider->method('isEnableAccountSync')->willReturn(false);

        $repo = $this->createMock(DataProviderRepository::class);
        $repo->method('findAll')->willReturn([$provider]);

        $command = new DataProviderListCommand($repo);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Leantime', $tester->getDisplay());
        $this->assertStringContainsString('true', $tester->getDisplay());
    }

    public function testExecuteEmptyProviders(): void
    {
        $repo = $this->createMock(DataProviderRepository::class);
        $repo->method('findAll')->willReturn([]);

        $command = new DataProviderListCommand($repo);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
