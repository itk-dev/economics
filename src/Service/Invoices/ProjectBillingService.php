<?php

namespace App\Service\Invoices;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use App\Repository\ClientRepository;
use App\Repository\ProjectBillingRepository;
use App\Repository\WorklogRepository;
use App\Service\ProjectTracker\ApiServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

class ProjectBillingService
{
    public const PROJECT_BILLING_NAME = 'projektfakturering';

    public function __construct(
        private readonly ProjectBillingRepository $projectBillingRepository,
        private readonly BillingService $billingService,
        private readonly ApiServiceInterface $apiService,
        private readonly ClientRepository $clientRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $receiverAccount,
    )
    {}

    /**
     * @throws \Exception
     */
    public function updateProjectBilling(int $projectBillingId): void
    {
        $projectBilling = $this->projectBillingRepository->find($projectBillingId);

        if ($projectBilling == null) {
            // TODO: Replace with custom exception.
            throw new \Exception("No project billing entity found.");
        }

        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->entityManager->remove($invoice);
        }

        $this->entityManager->flush();

        $this->createProjectBilling($projectBillingId);
    }

    public function createProjectBilling(int $projectBillingId): void
    {
        $projectBilling = $this->projectBillingRepository->find($projectBillingId);

        if ($projectBilling == null) {
            // TODO: Replace with custom exception.
            throw new \Exception("No project billing entity found.");
        }

        $projectBillingData = $this->apiService->getProjectBillingData($projectBilling->getProject()->getProjectTrackerId(), $projectBilling->getPeriodStart(), $projectBilling->getPeriodEnd());

        // For each invoiceData:
        // 1. Create an Invoice
        // 2. Foreach Issue in invoiceData:
        // 3.   Create an InvoiceEntry in the invoice
        // 4.   Connect worklogs from the database to the invoice entry.
        // 5. Set fields for invoice.
        foreach ($projectBillingData->invoices as $invoiceData) {
            $invoice = new Invoice();
            $invoice->setRecorded(false);
            $invoice->setProject($projectBilling->getProject());
            $invoice->setProjectBilling($projectBilling);
            $invoice->setDescription("Projektfakturering");
            $invoice->setName($projectBilling->getProject()->getName() . ": "  . $invoiceData->account->name . " (" . $projectBilling->getPeriodStart()->format("d/m/Y") . " - " .  $projectBilling->getPeriodEnd()->format("d/m/Y") . ")");
            $invoice->setPeriodFrom($projectBilling->getPeriodStart());
            $invoice->setPeriodTo($projectBilling->getPeriodEnd());
            $invoice->setCreatedBy(self::PROJECT_BILLING_NAME);
            $invoice->setCreatedAt(new \DateTime());
            $invoice->setUpdatedBy(self::PROJECT_BILLING_NAME);
            $invoice->setUpdatedAt(new \DateTime());

            $projectBilling->addInvoice($invoice);

            // Find client.
            $client = $this->clientRepository->findOneBy(['name' => $invoiceData->account->name, 'account' => $invoiceData->account->value]);

            if ($client != null) {
                $invoice->setClient($client);
            }

            $internal = $client->getType() == ClientTypeEnum::INTERNAL;

            // TODO: MaterialNumberEnum::EXTERNAL_WITH_MOMS or MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS?
            $invoice->setDefaultMaterialNumber($internal ? MaterialNumberEnum::INTERNAL : MaterialNumberEnum::EXTERNAL_WITH_MOMS);
            $invoice->setDefaultReceiverAccount($this->receiverAccount);

            $this->entityManager->persist($invoice);

            foreach ($invoiceData->issues as $issueData) {
                $invoiceEntry = new InvoiceEntry();
                $invoiceEntry->setInvoice($invoice);
                $invoice->addInvoiceEntry($invoiceEntry);
                $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
                $invoiceEntry->setDescription("");
                $invoiceEntry->setCreatedBy(self::PROJECT_BILLING_NAME);
                $invoiceEntry->setCreatedAt(new \DateTime());
                $invoiceEntry->setUpdatedBy(self::PROJECT_BILLING_NAME);
                $invoiceEntry->setUpdatedAt(new \DateTime());

                $product = $issueData->projectTrackerKey.":".preg_replace('/\(DEVSUPP-\d+\)/i', '', $issueData->name);
                $price = $client->getStandardPrice();

                $invoiceEntry->setProduct($product);
                $invoiceEntry->setPrice($price);
                $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());
                $invoiceEntry->setAccount($invoice->getDefaultReceiverAccount());

                $worklogs = $this->worklogRepository->findBy(['projectTrackerIssueId' => $issueData->projectTrackerId, 'invoiceEntry' => null]);

                /** @var Worklog $worklog */
                foreach ($worklogs as $worklog) {
                    if (!$worklog->isBilled()) {
                        $invoiceEntry->addWorklog($worklog);
                    }
                }

                $this->entityManager->persist($invoiceEntry);

                $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);
            }
        }

        $this->entityManager->flush();

        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->billingService->updateInvoiceTotalPrice($invoice);
        }

        $this->entityManager->flush();
    }
}
