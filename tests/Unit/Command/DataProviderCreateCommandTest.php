<?php

namespace App\Tests\Unit\Command;

use App\Command\DataProviderCreateCommand;
use App\Entity\DataProvider;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DataProviderCreateCommandTest extends TestCase
{
    public function testAnswersArePassedToTheService(): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName('Leantime 1');
        $dataProvider->setUrl('https://leantime.example');
        $dataProvider->setClass(DataProviderService::IMPLEMENTATIONS[0]);
        $dataProvider->setEnableClientSync(true);
        $dataProvider->setEnableAccountSync(false);

        $service = $this->createMock(DataProviderService::class);
        $service->expects($this->once())
            ->method('createDataProvider')
            ->with('Leantime 1', DataProviderService::IMPLEMENTATIONS[0], 'https://leantime.example', 's3cret')
            ->willReturn($dataProvider);

        $tester = new CommandTester(new DataProviderCreateCommand($service));
        $tester->setInputs(['Leantime 1', 'https://leantime.example', 's3cret', DataProviderService::IMPLEMENTATIONS[0]]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCreatedProviderIsStampedAsCliAuthored(): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName('Leantime 1');

        $service = $this->createMock(DataProviderService::class);
        $service->method('createDataProvider')->willReturn($dataProvider);

        $tester = new CommandTester(new DataProviderCreateCommand($service));
        $tester->setInputs(['Leantime 1', 'https://leantime.example', 's3cret', DataProviderService::IMPLEMENTATIONS[0]]);
        $tester->execute([]);

        $this->assertSame('CLI', $dataProvider->getCreatedBy());
        $this->assertSame('CLI', $dataProvider->getUpdatedBy());
    }

    public function testSummaryIsPrintedWithoutTheSecret(): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName('Leantime 1');
        $dataProvider->setUrl('https://leantime.example');
        $dataProvider->setSecret('s3cret');
        $dataProvider->setClass(DataProviderService::IMPLEMENTATIONS[0]);

        $service = $this->createMock(DataProviderService::class);
        $service->method('createDataProvider')->willReturn($dataProvider);

        $tester = new CommandTester(new DataProviderCreateCommand($service));
        $tester->setInputs(['Leantime 1', 'https://leantime.example', 's3cret', DataProviderService::IMPLEMENTATIONS[0]]);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Leantime 1', $display);
        $this->assertStringContainsString('https://leantime.example', $display);
        $this->assertStringContainsString('Secret: ****', $display);
        $this->assertStringNotContainsString('s3cret', $display);
    }
}
