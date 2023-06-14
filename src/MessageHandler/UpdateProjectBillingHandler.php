<?php

namespace App\MessageHandler;

use App\Message\UpdateProjectBillingMessage;
use App\Service\Invoices\ProjectBillingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateProjectBillingHandler
{
    public function __construct(private readonly ProjectBillingService $projectBillingService)
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(UpdateProjectBillingMessage $message): void
    {
        $this->projectBillingService->updateProjectBilling($message->getProjectBillingId());
    }
}
