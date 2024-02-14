<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\ProjectBilling;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use App\Exception\EconomicsException;
use App\Exception\InvoiceAlreadyOnRecordException;
use App\Repository\ClientRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectBillingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProjectBillingService
{
    public const PROJECT_BILLING_VERSION_PREFIX = 'PB-';

    public function __construct(
        private readonly ProjectBillingRepository $projectBillingRepository,
        private readonly BillingService $billingService,
        private readonly IssueRepository $issueRepository,
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly string $invoiceDefaultReceiverAccount,
    ) {
    }

    /**
     * @throws EconomicsException
     * @throws \Exception
     */
    public function getIssuesNotIncludedInProjectBilling(ProjectBilling $projectBilling): array
    {
        $project = $projectBilling->getProject();
        $periodStart = $projectBilling->getPeriodStart();
        $periodEnd = $projectBilling->getPeriodEnd();

        if (null == $project) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_no_project_selected'));
        }

        if (null == $periodStart || null == $periodEnd) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_period_cannot_be_null'));
        }

        $from = new \DateTime($periodStart->format('Y-m-d').' 00:00:00');
        $to = new \DateTime($periodEnd->format('Y-m-d').' 23:59:59');

        $issues = $this->issueRepository->getClosedIssuesFromInterval($project, $from, $to);

        $includedProducts = [];

        foreach ($projectBilling->getInvoices() as $invoice) {
            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                $includedProducts[] = $invoiceEntry->getProduct();
            }
        }

        $filteredIssues = [];

        foreach ($issues as $issue) {
            $product = $this->getInvoiceEntryProduct($issue);

            if (!in_array($product, $includedProducts)) {
                $filteredIssues[] = $issue;
            }
        }

        return $filteredIssues;
    }

    /**
     * @throws EconomicsException
     */
    public function updateProjectBilling(int $projectBillingId): void
    {
        $projectBilling = $this->projectBillingRepository->find($projectBillingId);

        if (null == $projectBilling) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_no_entity_found'));
        }

        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->entityManager->remove($invoice);
        }

        $this->entityManager->flush();

        $this->createProjectBilling($projectBillingId);
    }

    /**
     * @throws EconomicsException
     */
    public function createProjectBilling(int $projectBillingId): void
    {
        $projectBilling = $this->projectBillingRepository->find($projectBillingId);

        if (null == $projectBilling) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_no_entity_found'));
        }

        $project = $projectBilling->getProject();

        if (null == $project) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_no_project_selected'));
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null == $projectTrackerId) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_no_project_project_tracker_id'));
        }

        $periodStart = $projectBilling->getPeriodStart();
        $periodEnd = $projectBilling->getPeriodEnd();

        if (null == $periodStart || null == $periodEnd) {
            throw new EconomicsException($this->translator->trans('exception.project_billing_period_cannot_be_null'));
        }

        // Find issues in interval
        // Group by client
        // Foreach client
        //   Create invoice
        //     Create an InvoiceEntry in the invoice
        //     Connect worklogs from the database to the invoice entry.
        $issues = $this->issueRepository->getClosedIssuesFromInterval($project, $periodStart, $periodEnd);

        // TODO: Replace with Model.
        $invoices = [];

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            $foundProjectBillingVersions = [];

            foreach ($issue->getVersions() as $version) {
                $name = $issue->getName();
                if (null !== $name && str_starts_with($name, self::PROJECT_BILLING_VERSION_PREFIX)) {
                    $foundProjectBillingVersions[] = $version;
                }
            }

            if (1 !== count($foundProjectBillingVersions)) {
                continue;
            }

            $foundVersion = $foundProjectBillingVersions[0];

            // Find the client.
            $client = $this->clientRepository->findOneBy(['versionName' => $foundVersion->getName()]);

            if (null === $client) {
                continue;
            }

            $clientId = $client->getId();

            if (null !== $clientId) {
                if (!isset($invoices[$clientId])) {
                    $invoices[$clientId] = [
                        'client' => $client,
                        'issues' => [],
                    ];
                }

                $invoices[$clientId]['issues'][] = $issue;
            }
        }

        foreach ($invoices as $invoiceArray) {
            /** @var Client $client */
            $client = $invoiceArray['client'];

            $invoice = new Invoice();
            $invoice->setRecorded(false);
            $invoice->setProject($projectBilling->getProject());
            $invoice->setProjectBilling($projectBilling);
            $invoice->setDescription($projectBilling->getDescription());
            $invoice->setName($project->getName().': '.$client->getName().' ('.$periodStart->format('d/m/Y').' - '.$periodEnd->format('d/m/Y').')');
            $invoice->setPeriodFrom($periodStart);
            $invoice->setPeriodTo($periodEnd);
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

                $product = $this->getInvoiceEntryProduct($issue);
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

    public function getInvoiceEntryProduct(Issue $issue): string
    {
        return $issue->getProjectTrackerKey().':'.preg_replace('/\(DEVSUPP-\d+\)/i', '', $issue->getName() ?? '');
    }
}
