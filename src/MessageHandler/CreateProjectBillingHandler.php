<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\CreateProjectBillingMessage;
use App\Service\ProjectBillingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CreateProjectBillingHandler
{
    public function __construct(private ProjectBillingService $projectBillingService)
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
