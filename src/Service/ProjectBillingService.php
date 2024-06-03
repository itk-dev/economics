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
use App\Model\Invoices\ConfirmData;
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
        private readonly ClientHelper $clientHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly InvoiceEntryHelper $invoiceEntryHelper,
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

        $filteredIssues = [];

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            $worklogs = $issue->getWorklogs();
            if ($worklogs->isEmpty()) {
                foreach ($issue->getProducts() as $product) {
                    if (!$product->isBilled() && null === $product->getInvoiceEntry()) {
                        $filteredIssues[] = $issue;
                        break;
                    }
                }
            } else {
                foreach ($worklogs as $worklog) {
                    if (!$worklog->isBilled() && $worklog->getTimeSpentSeconds() > 0 && null === $worklog->getInvoiceEntry()) {
                        $filteredIssues[] = $issue;
                        break;
                    }
                }
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

        // Delete all non-recorded (ikke-bogført) invoices.
        foreach ($projectBilling->getInvoices() as $invoice) {
            if (!$invoice->isRecorded()) {
                $this->entityManager->remove($invoice);
            }
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
        $issues = $this->getIssuesNotIncludedInProjectBilling($projectBilling);

        // TODO: Replace with Model.
        $invoices = [];

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            $foundProjectBillingVersions = [];

            foreach ($issue->getVersions() as $version) {
                $name = $version->getName();
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
            $invoice->setDefaultReceiverAccount($this->invoiceEntryHelper->getDefaultAccount());

            /** @var Issue $issue */
            foreach ($invoiceArray['issues'] as $issue) {
                $invoiceEntry = new InvoiceEntry();
                $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
                $invoiceEntry->setDescription('');

                $product = $this->getInvoiceEntryProduct($issue);
                $price = $this->clientHelper->getStandardPrice($client);

                $invoiceEntry->setProduct($product);
                $invoiceEntry->setPrice($price);
                $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());
                $invoiceEntry->setAccount($invoice->getDefaultReceiverAccount());

                $worklogs = $issue->getWorklogs();

                /** @var Worklog $worklog */
                foreach ($worklogs as $worklog) {
                    // The invoice entry in the worklog may be non-null, but the entry may have a null id
                    // @TODO Investigate why this is the case
                    if (!$worklog->isBilled() && null === $worklog->getInvoiceEntry()?->getId()) {
                        $invoiceEntry->addWorklog($worklog);
                    }
                }

                // We only want to invoice entries with worklogs or products.
                if ($invoiceEntry->getWorklogs()->isEmpty()
                    && $issue->getProducts()->isEmpty()) {
                    continue;
                }

                if (!$invoiceEntry->getWorklogs()->isEmpty()) {
                    $invoice->addInvoiceEntry($invoiceEntry);
                    $this->entityManager->persist($invoiceEntry);
                }

                $invoiceEntryProductName = $invoiceEntry->getProduct();
                // Add invoice entries for each product.
                foreach ($issue->getProducts() as $productIssue) {
                    $product = $productIssue->getProduct();
                    if (null === $product) {
                        continue;
                    }

                    $productName = $product->getName() ?? '';
                    $productInvoiceEntry = (new InvoiceEntry())
                        ->setEntryType(InvoiceEntryTypeEnum::PRODUCT)
                        ->setDescription($productIssue->getDescription())
                        ->setProduct(null === $invoiceEntryProductName
                            ? $productName
                            : sprintf('%s: %s', $invoiceEntryProductName, $productName)
                        )
                        ->setPrice($product->getPriceAsFloat())
                        ->setAmount($productIssue->getQuantity())
                        ->setTotalPrice($productIssue->getQuantity() * $product->getPriceAsFloat())
                        ->setMaterialNumber($invoice->getDefaultMaterialNumber())
                        ->setAccount($this->invoiceEntryHelper->getProductAccount()
                            ?? $this->invoiceEntryHelper->getDefaultAccount())
                        ->addIssueProduct($productIssue);
                    // We don't add worklogs here, since they're already attached to the main invoice entry
                    // (and only used to detect if an entry has been added to an invoice).

                    $invoice->addInvoiceEntry($productInvoiceEntry);
                    $this->entityManager->persist($productInvoiceEntry);
                }
            }

            if ($invoice->getInvoiceEntries()->isEmpty()) {
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
                // Project billing should never be recorded as "no cost".
                $this->billingService->recordInvoice($invoice, confirmation: ConfirmData::INVOICE_RECORD_YES, flush: false);
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
