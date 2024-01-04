<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\CreateProjectBillingMessage;
use App\Service\ProjectBillingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateProjectBillingHandler
{
    public function __construct(private readonly ProjectBillingService $projectBillingService)
    {
    }

    /**
     * @throws EconomicsException
     */
    public function __invoke(CreateProjectBillingMessage $message): void
    {
        $this->projectBillingService->createProjectBilling($message->getProjectBillingId());
    }
}
