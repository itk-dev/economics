<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\UpdateProjectBillingMessage;
use App\Service\ProjectBillingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateProjectBillingHandler
{
    public function __construct(private readonly ProjectBillingService $projectBillingService)
    {
    }

    /**
     * @throws EconomicsException
     */
    public function __invoke(UpdateProjectBillingMessage $message): void
    {
        $this->projectBillingService->updateProjectBilling($message->getProjectBillingId());
    }
}
