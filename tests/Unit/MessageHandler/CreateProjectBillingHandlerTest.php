<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\CreateProjectBillingMessage;
use App\MessageHandler\CreateProjectBillingHandler;
use App\Service\ProjectBillingService;
use PHPUnit\Framework\TestCase;

class CreateProjectBillingHandlerTest extends TestCase
{
    public function testInvokeCallsCreateProjectBilling(): void
    {
        $message = new CreateProjectBillingMessage(42);

        $service = $this->createMock(ProjectBillingService::class);
        $service->expects($this->once())->method('createProjectBilling')->with(42);

        $handler = new CreateProjectBillingHandler($service);
        $handler($message);
    }
}
