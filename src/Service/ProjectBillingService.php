<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\ProjectBilling;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use App\Exception\InvoiceAlreadyOnRecordException;
use App\Repository\AccountRepository;
use App\Repository\ClientRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectBillingRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectBillingService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly ProjectBillingRepository $projectBillingRepository,
        private readonly BillingService $billingService,
        private readonly IssueRepository $issueRepository,
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $invoiceDefaultReceiverAccount,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function updateProjectBilling(int $projectBillingId): void
    {
        $projectBilling = $this->projectBillingRepository->find($projectBillingId);

        if (null == $projectBilling) {
            // TODO: Replace with custom exception.
            throw new \Exception('No project billing entity found.');
        }

        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->entityManager->remove($invoice);
        }

        $this->entityManager->flush();

        $this->createProjectBilling($projectBillingId);
    }

    /**
     * @throws \Exception
     */
    public function createProjectBilling(int $projectBillingId): void
    {
        $projectBilling = $this->projectBillingRepository->find($projectBillingId);

        // TODO: Replace with custom exception.
        if (null == $projectBilling) {
            throw new \Exception('No project billing entity found.');
        }

        $project = $projectBilling->getProject();

        if (null == $project) {
            throw new \Exception('No project selected.');
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null == $projectTrackerId) {
            throw new \Exception('No project.projectTrackerId.');
        }

        $periodStart = $projectBilling->getPeriodStart();
        $periodEnd = $projectBilling->getPeriodEnd();

        if (null == $periodStart || null == $periodEnd) {
            throw new \Exception('project.periodStart or project.periodEnd cannot be null.');
        }

        // Find issues in interval
        // Group by account
        // Foreach Account
        // Create invoice
        //   Create an InvoiceEntry in the invoice
        //   Connect worklogs from the database to the invoice entry.
        $issues = $this->issueRepository->getClosedIssuesFromInterval($project, $periodStart, $periodEnd);

        // TODO: Replace with Model.
        $invoices = [];

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            if (null !== $issue->getAccountId()) {
                $accountId = $issue->getAccountId();

                if (!is_null($accountId)) {
                    if (!isset($invoices[$accountId])) {
                        $account = $this->accountRepository->findOneBy(['projectTrackerId' => $issue->getAccountId()]);

                        if (null == $account) {
                            continue;
                        }

                        $invoices[$accountId] = [
                            'account' => $account,
                            'issues' => [],
                        ];
                    }

                    $invoices[$accountId]['issues'][] = $issue;
                }
            }
        }

        foreach ($invoices as $invoiceArray) {
            /** @var Account $account */
            $account = $invoiceArray['account'];

            $invoice = new Invoice();
            $invoice->setRecorded(false);
            $invoice->setProject($projectBilling->getProject());
            $invoice->setProjectBilling($projectBilling);
            $invoice->setDescription($projectBilling->getDescription());
            $invoice->setName($project->getName().': '.$account->getName().' ('.$periodStart->format('d/m/Y').' - '.$periodEnd->format('d/m/Y').')');
            $invoice->setPeriodFrom($periodStart);
            $invoice->setPeriodTo($periodEnd);

            // Find client.
            $client = $this->clientRepository->findOneBy(['name' => $account->getName(), 'account' => $account->getValue()]);

            // Ignore invoices where there is not client.
            if (null == $client) {
                continue;
            }

            $invoice->setClient($client);

            $internal = ClientTypeEnum::INTERNAL == $client->getType();

            // TODO: MaterialNumberEnum::EXTERNAL_WITH_MOMS or MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS?
            $invoice->setDefaultMaterialNumber($internal ? MaterialNumberEnum::INTERNAL : MaterialNumberEnum::EXTERNAL_WITH_MOMS);
            $invoice->setDefaultReceiverAccount($this->invoiceDefaultReceiverAccount);

            /** @var Issue $issue */
            foreach ($invoiceArray['issues'] as $issue) {
                $invoiceEntry = new InvoiceEntry();
                $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
                $invoiceEntry->setDescription('');

                $product = $issue->getProjectTrackerKey().':'.preg_replace('/\(DEVSUPP-\d+\)/i', '', $issue->getName() ?? '');
                $price = $client->getStandardPrice();

                $invoiceEntry->setProduct($product);
                $invoiceEntry->setPrice($price);
                $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());
                $invoiceEntry->setAccount($invoice->getDefaultReceiverAccount());

                $worklogs = $issue->getWorklogs();

                /** @var Worklog $worklog */
                foreach ($worklogs as $worklog) {
                    if (!$worklog->isBilled()) {
                        $invoiceEntry->addWorklog($worklog);
                    }
                }

                if (0 == count($invoiceEntry->getWorklogs())) {
                    continue;
                }

                $invoiceEntry->setInvoice($invoice);
                $invoice->addInvoiceEntry($invoiceEntry);
                $this->entityManager->persist($invoiceEntry);
            }

            if (0 == count($invoice->getInvoiceEntries())) {
                continue;
            }

            $projectBilling->addInvoice($invoice);
            $this->entityManager->persist($invoice);
        }

        $this->entityManager->flush();

        foreach ($projectBilling->getInvoices() as $invoice) {
            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);
            }

            $this->billingService->updateInvoiceTotalPrice($invoice);
        }

        $this->entityManager->flush();
    }

    /**
     * @throws \Exception
     */
    public function recordProjectBilling(ProjectBilling $projectBilling): void
    {
        foreach ($projectBilling->getInvoices() as $invoice) {
            try {
                $this->billingService->recordInvoice($invoice, false);
            } catch (InvoiceAlreadyOnRecordException) {
                // Ignore if invoice is already on record.
            }
        }

        // Persist the changes to invoices.
        $this->entityManager->flush();

        $projectBilling->setRecorded(true);
        $this->projectBillingRepository->save($projectBilling, true);
    }
}
