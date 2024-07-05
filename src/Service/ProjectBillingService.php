<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\IssueProduct;
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
        private readonly InvoiceHelper $invoiceHelper,
    ) {
    }

    public function getIssuesNotIncludedInProjectBillingFromTheFarPast(ProjectBilling $projectBilling): array
    {
        $issues = null;

        if ($cutoffDate = $this->invoiceHelper->getIssueFarPastCutoffDate()) {
            $projectBilling = clone $projectBilling;
            $projectBilling->setPeriodStart(new \DateTimeImmutable('0001-01-01'));
            $projectBilling->setPeriodEnd($cutoffDate);

            $issues = $this->getIssuesNotIncludedInProjectBilling($projectBilling);
        }

        return [
            'cutoff_date' => $cutoffDate,
            'issues' => $issues,
        ];
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

            $invoiceKey = $client->getId();

            if (null !== $invoiceKey) {
                if ($this->invoiceHelper->getOneInvoicePerIssue()) {
                    $invoiceKey .= '|||'.$issue->getId();
                }

                if (!isset($invoices[$invoiceKey])) {
                    $invoices[$invoiceKey] = [
                        'client' => $client,
                        'issues' => [],
                    ];
                }

                $invoices[$invoiceKey]['issues'][] = $issue;
            }
        }

        $defaultAccount = $this->invoiceHelper->getDefaultAccount();
        $productAccount = $this->invoiceHelper->getProductAccount();

        // Add invoice entry account prefix, if any, to (product) name.
        $prefixWithAccount = function (?string $account, ?string $name): ?string {
            if (null === $name) {
                return null;
            }

            $prefix = $account ? $this->invoiceHelper->getAccountInvoiceEntryPrefix($account) : null;
            if (null !== $prefix) {
                $name = $prefix.': '.$name;
            }

            return $name;
        };

        foreach ($invoices as $invoiceArray) {
            /** @var Client $client */
            $client = $invoiceArray['client'];

            $invoiceName = sprintf('%s: %s (%s - %s)',
                $project->getName() ?? '',
                $client->getName() ?? '',
                $periodStart->format('d/m/Y'),
                $periodEnd->format('d/m/Y')
            );
            if ($this->invoiceHelper->getOneInvoicePerIssue()) {
                // We know that we have exactly one issue.
                $issue = reset($invoiceArray['issues']);
                $invoiceName = $issue->getName().': '.$invoiceName;
            }

            $invoice = new Invoice();
            $invoice->setRecorded(false);
            $invoice->setProject($projectBilling->getProject());
            $invoice->setProjectBilling($projectBilling);
            $invoice->setDescription($projectBilling->getDescription());
            $invoice->setName($invoiceName);
            $invoice->setPeriodFrom($periodStart);
            $invoice->setPeriodTo($periodEnd);
            $invoice->setClient($client);

            $internal = ClientTypeEnum::INTERNAL == $client->getType();

            // TODO: MaterialNumberEnum::EXTERNAL_WITH_MOMS or MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS?
            $invoice->setDefaultMaterialNumber($internal ? MaterialNumberEnum::INTERNAL : MaterialNumberEnum::EXTERNAL_WITH_MOMS);
            $invoice->setDefaultReceiverAccount($defaultAccount);

            /** @var Issue $issue */
            foreach ($invoiceArray['issues'] as $issue) {
                $invoiceEntry = new InvoiceEntry();
                $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
                $invoiceEntry->setDescription('');

                $product = $prefixWithAccount(
                    $defaultAccount,
                    $issue->getName()
                );
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

                // Add a single product entry summing all product expenses.
                $products = $issue->getProducts();
                if (!$products->isEmpty()) {
                    $price = $products->reduce(static fn (?float $sum, IssueProduct $product) => $sum + $product->getTotal(), 0.0);
                    $product = $prefixWithAccount(
                        $productAccount,
                        $issue->getName()
                    );
                    $productInvoiceEntry = (new InvoiceEntry())
                        ->setEntryType(InvoiceEntryTypeEnum::PRODUCT)
                        ->setDescription($issue->getName())
                        ->setProduct($product)
                        ->setPrice($price)
                        ->setAmount(1)
                        ->setTotalPrice($price)
                        ->setMaterialNumber($invoice->getDefaultMaterialNumber())
                        ->setAccount($productAccount ?? $this->invoiceHelper->getDefaultAccount());
                    foreach ($issue->getProducts() as $productIssue) {
                        $productInvoiceEntry->addIssueProduct($productIssue);
                    }
                    // We don't add worklogs here, since they're already attached to the main invoice entry
                    // (and only used to detect if an entry has been added to an invoice).

                    $invoice->addInvoiceEntry($productInvoiceEntry);
                    $this->entityManager->persist($productInvoiceEntry);
                }
            }

            if ($invoice->getInvoiceEntries()->isEmpty()) {
                continue;
            }

            if ($this->invoiceHelper->getOneInvoicePerIssue()
                && $this->invoiceHelper->getSetInvoiceDescriptionFromIssueDescription()) {
                // We know that we have exactly one issue.
                $issue = reset($invoiceArray['issues']);
                if ($description = $this->invoiceHelper->getInvoiceDescription($issue->getDescription())) {
                    $invoice->setDescription($description);
                }
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
