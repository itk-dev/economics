<?php

namespace App\MessageHandler;

use App\Message\CreateProjectBillingMessage;
use App\Service\Invoices\ProjectBillingService;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateProjectBillingHandler
{
    public function __construct(private readonly ProjectBillingService $projectBillingService)
    {
    }

    /**
     * @throws Exception
     */
    public function __invoke(CreateProjectBillingMessage $message): void
    {
        $this->projectBillingService->createProjectBilling($message->getProjectBillingId());
    }
}
