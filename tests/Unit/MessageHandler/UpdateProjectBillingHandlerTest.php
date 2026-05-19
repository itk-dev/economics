<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\UpdateProjectBillingMessage;
use App\MessageHandler\UpdateProjectBillingHandler;
use App\Service\ProjectBillingService;
use PHPUnit\Framework\TestCase;

class UpdateProjectBillingHandlerTest extends TestCase
{
    public function testInvokeCallsUpdateProjectBilling(): void
    {
        $message = new UpdateProjectBillingMessage(42);

        $service = $this->createMock(ProjectBillingService::class);
        $service->expects($this->once())->method('updateProjectBilling')->with(42);

        $handler = new UpdateProjectBillingHandler($service);
        $handler($message);
    }
}
