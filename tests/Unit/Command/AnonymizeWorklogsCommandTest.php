<?php

namespace App\Tests\Unit\Command;

use App\Command\AnonymizeWorklogsCommand;
use App\Service\AnonymizeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class AnonymizeWorklogsCommandTest extends TestCase
{
    public function testAnonymizesWorklogsOlderThanFiveYears(): void
    {
        $anonymizeService = $this->createMock(AnonymizeService::class);

        $fiveYearsAgo = (new \DateTime())->sub(new \DateInterval('P5Y'));

        $anonymizeService->expects($this->once())
            ->method('anonymizeWorklogs')
            ->with($this->callback(fn (\DateTimeInterface $anonymizeBefore) => abs($anonymizeBefore->getTimestamp() - $fiveYearsAgo->getTimestamp()) < 60))
            ->willReturn(3);

        $tester = new CommandTester(new AnonymizeWorklogsCommand($anonymizeService));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Anonymized 3 worklogs', $tester->getDisplay());
        $this->assertStringContainsString($fiveYearsAgo->format('Y-m-d'), $tester->getDisplay());
    }

    public function testReportsWhenNothingWasAnonymized(): void
    {
        $anonymizeService = $this->createMock(AnonymizeService::class);
        $anonymizeService->method('anonymizeWorklogs')->willReturn(0);

        $tester = new CommandTester(new AnonymizeWorklogsCommand($anonymizeService));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Anonymized 0 worklogs', $tester->getDisplay());
    }
}
