<?php

namespace App\Tests\Unit\Command;

use App\Command\DataProviderSetEnableCommand;
use App\Entity\DataProvider;
use App\Repository\DataProviderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DataProviderSetEnableCommandTest extends TestCase
{
    public function testEnableProvider(): void
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getId')->willReturn(1);
        $provider->method('isEnabled')->willReturn(true);
        $provider->expects($this->once())->method('setEnabled')->with(true);
        $provider->expects($this->once())->method('setUpdatedBy')->with('CLI');

        $repo = $this->createMock(DataProviderRepository::class);
        $repo->method('find')->with(1)->willReturn($provider);
        $repo->expects($this->once())->method('save')->with($provider, true);

        $command = new DataProviderSetEnableCommand($repo);
        $tester = new CommandTester($command);
        $tester->execute(['id' => '1', 'enable' => 'true']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testDisableProvider(): void
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getId')->willReturn(1);
        $provider->method('isEnabled')->willReturn(false);
        $provider->expects($this->once())->method('setEnabled')->with(false);

        $repo = $this->createMock(DataProviderRepository::class);
        $repo->method('find')->with(1)->willReturn($provider);

        $command = new DataProviderSetEnableCommand($repo);
        $tester = new CommandTester($command);
        $tester->execute(['id' => '1', 'enable' => 'false']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testInvalidIdReturnsFailure(): void
    {
        $repo = $this->createMock(DataProviderRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $command = new DataProviderSetEnableCommand($repo);
        $tester = new CommandTester($command);
        $tester->execute(['id' => '999', 'enable' => 'true']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }
}
