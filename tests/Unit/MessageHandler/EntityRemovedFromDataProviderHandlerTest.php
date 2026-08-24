<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Exception\NotFoundException;
use App\Message\EntityRemovedFromDataProviderMessage;
use App\MessageHandler\EntityRemovedFromDataProviderHandler;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class EntityRemovedFromDataProviderHandlerTest extends TestCase
{
    private DataProviderService $service;
    private EntityRemovedFromDataProviderHandler $handler;

    protected function setUp(): void
    {
        $this->service = $this->createMock(DataProviderService::class);
        $this->handler = new EntityRemovedFromDataProviderHandler(
            $this->createMock(LoggerInterface::class),
            $this->service,
        );
    }

    public function testProjectClassCallsProjectRemoved(): void
    {
        $deletedDate = new \DateTime();
        $message = new EntityRemovedFromDataProviderMessage(Project::class, 1, 'PT-1', $deletedDate);

        $this->service->expects($this->once())
            ->method('projectRemovedFromDataProvider')
            ->with(1, 'PT-1', $deletedDate);

        ($this->handler)($message);
    }

    public function testVersionClassCallsVersionRemoved(): void
    {
        $message = new EntityRemovedFromDataProviderMessage(Version::class, 1, 'VER-1', null);

        $this->service->expects($this->once())
            ->method('versionRemovedFromDataProvider')
            ->with(1, 'VER-1');

        ($this->handler)($message);
    }

    public function testIssueClassCallsIssueRemoved(): void
    {
        $deletedDate = new \DateTime();
        $message = new EntityRemovedFromDataProviderMessage(Issue::class, 1, 'ISS-1', $deletedDate);

        $this->service->expects($this->once())
            ->method('issueRemovedFromDataProvider')
            ->with(1, 'ISS-1', $deletedDate);

        ($this->handler)($message);
    }

    public function testWorklogClassCallsWorklogRemoved(): void
    {
        $deletedDate = new \DateTime();
        $message = new EntityRemovedFromDataProviderMessage(Worklog::class, 1, '100', $deletedDate);

        $this->service->expects($this->once())
            ->method('worklogRemovedFromDataProvider');

        ($this->handler)($message);
    }

    public function testRowLevelFailureThrowsUnrecoverable(): void
    {
        $message = new EntityRemovedFromDataProviderMessage(Project::class, 1, 'PT-1', null);

        $this->service->method('projectRemovedFromDataProvider')
            ->willThrowException(new NotFoundException('fail'));

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)($message);
    }

    public function testInfrastructureFailurePropagates(): void
    {
        $message = new EntityRemovedFromDataProviderMessage(Project::class, 1, 'PT-1', null);

        $this->service->method('projectRemovedFromDataProvider')
            ->willThrowException(new \RuntimeException('the database went away'));

        // Marking this unrecoverable would drop the message and let the caller record it as a
        // skipped row, so a broken run would still report success.
        try {
            ($this->handler)($message);
            $this->fail('Expected the failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            $this->assertSame('the database went away', $e->getMessage());
        }
    }
}
