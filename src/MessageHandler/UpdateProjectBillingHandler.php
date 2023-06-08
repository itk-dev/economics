<?php

namespace App\MessageHandler;

use App\Message\CreateProjectBillingMessage;
use App\Message\UpdateProjectBillingMessage;
use App\Service\Invoices\ProjectBillingService;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateProjectBillingHandler
{
    public function __construct(private readonly ProjectBillingService $projectBillingService)
    {
    }

    /**
     * @throws Exception
     */
    public function __invoke(UpdateProjectBillingMessage $message): void
    {
        $this->projectBillingService->updateProjectBilling($message->getProjectBillingId());
    }
}
