<?php

/*
 * This file is part of aakb/jira_economics.
 *
 * (c) 2019 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Service\Invoices;

use App\Entity\Expense;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Repository\ExpenseRepository;
use App\Repository\InvoiceRepository;
use App\Service\ProjectTracker\ApiServiceInterface;
use App\Repository\WorklogRepository;
use Billing\Exception\InvoiceException;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceService
{
    private WorklogRepository $worklogRepository;
    private ExpenseRepository $expenseRepository;
    private InvoiceRepository $invoiceRepository;
    private $boundReceiverAccount;
    private CacheProvider $cache;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly ApiServiceInterface $apiService,
    ) {
    }

    /**
     * Get invoices for specific Jira project.
     */
    public function getInvoices(int $jiraProjectId): array
    {
        if (!(int) $jiraProjectId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $repository = $this->entityManager->getRepository(Project::class);
        $project = $repository->findOneBy(['jiraId' => $jiraProjectId]);

        if (!$project) {
            throw new HttpException(404, 'Project with id '.$jiraProjectId.' not found');
        }

        $invoices = [];
        foreach ($project->getInvoices() as $invoice) {
            $invoices[] = $this->getInvoiceArray($invoice);
        }

        return $invoices;
    }

    /**
     * Get all invoices.
     *
     * @return array
     */
    public function getAllInvoices(): array
    {
        $repository = $this->entityManager->getRepository(Invoice::class);
        $invoices = $repository->findAll();

        if (!$invoices) {
            return [];
        }

        $invoicesArray = [];
        foreach ($invoices as $invoice) {
            $invoicesArray[] = $this->getInvoiceArray($invoice);
        }

        return $invoicesArray;
    }

    /**
     * Get specific invoice by id.
     */
    public function getInvoice(int $invoiceId): array
    {
        if (!(int) $invoiceId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $repository = $this->entityManager->getRepository(Invoice::class);
        $invoice = $repository->findOneBy(['id' => $invoiceId]);

        if (!$invoice) {
            throw new HttpException(404, 'Invoice with id '.$invoiceId.' not found');
        }

        return $this->getInvoiceArray($invoice, true);
    }

    /**
     * Get invoice as array.
     */
    private function getInvoiceArray(Invoice $invoice, bool $withAccount = false): array
    {
        $account = null;

        // Get account information.
        if ($withAccount) {
            try {
                $account = $this->apiService->getAccount($invoice->getCustomerAccountId());
                $account->defaultPrice = $this->getAccountDefaultPrice($invoice->getCustomerAccountId());
            } catch (Exception $exception) {
                $account = null;
            }
        }

        $totalPrice = array_reduce($invoice->getInvoiceEntries()->toArray(), function (int $carry, InvoiceEntry $entry) {
            return $carry + $entry->getAmount() * $entry->getPrice();
        }, 0);

        return [
            'id' => $invoice->getId(),
            'name' => $invoice->getName(),
            'projectId' => $invoice->getProject()->getRemoteId(),
            'projectName' => $invoice->getProject()->getName(),
            'jiraId' => $invoice->getProject()->getRemoteId(),
            'recorded' => $invoice->isRecorded(),
            'accountId' => $invoice->getCustomerAccountId(),
            'description' => $invoice->getDescription(),
            'paidByAccount' => $invoice->getPaidByAccount(),
            'account' => $account,
            'totalPrice' => $totalPrice,
            'exportedDate' => $invoice->getExportedDate()?->format('c'),
            'created' => $invoice->getCreatedAt()->format('c'),
            'created_by' => $invoice->getCreatedBy(),
            'defaultPayToAccount' => $invoice->getDefaultPayToAccount(),
            'defaultMaterialNumber' => $invoice->getDefaultMaterialNumber(),
            'periodFrom' => $invoice->getPeriodFrom()?->format('U'),
            'periodTo' => $invoice->getPeriodTo()?->format('U'),
        ];
    }

    /**
     * Post new invoice, creating a new entity referenced by the returned id.
     *
     * @throws Exception
     */
    public function postInvoice(array $invoiceData): array
    {
        if (empty($invoiceData['projectId']) || !(int) ($invoiceData['projectId'])) {
            throw new HttpException(400, "Expected integer value for 'projectId' in request");
        }

        if (empty($invoiceData['name'])) {
            throw new HttpException(400, "Expected 'name' in request");
        }

        $repository = $this->entityManager->getRepository(Project::class);
        $project = $repository->findOneBy(['jiraId' => $invoiceData['projectId']]);

        // If project is not present in db, add it from Jira.
        if (!$project) {
            $project = $this->getProject($invoiceData['projectId']);
        }

        $invoice = new Invoice();
        $invoice->setName($invoiceData['name']);
        $invoice->setProject($project);
        $invoice->setRecorded(false);
        $invoice->setCustomerAccountId((int) $invoiceData['customerAccountId']);

        if (isset($invoiceData['periodFrom'])) {
            $from = $invoiceData['periodFrom'];
            $invoice->setPeriodFrom(new \DateTime($from));
        }

        if (isset($invoiceData['periodTo'])) {
            $to = $invoiceData['periodTo'];
            $invoice->setPeriodTo(new \DateTime($to));
        }

        // Set project default description.
        $project = $this->getProject($invoiceData['projectId']);
        $lead = $project->lead ?? null; // $this->getUser($project->lead->key) : null;
        $leadName = $lead->displayName ?? '';
        $leadMail = $lead->emailAddress ?? '';
        $description = $this->translator->trans(
            'invoice_default_description',
            ['%invoiceName%' => $invoice->getName(), '%leadName%' => $leadName, '%leadMail%' => $leadMail]
        );
        $invoice->setDescription($description);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $this->getInvoiceArray($invoice, true);
    }

    /**
     * Put specific invoice, replacing the invoice referenced by the given id.
     *
     * @throws Exception
     */
    public function putInvoice(array $invoiceData): array
    {
        if (empty($invoiceData['id']) || !(int) ($invoiceData['id'])) {
            throw new HttpException(400, "Expected integer value for 'id' in request");
        }

        $repository = $this->entityManager->getRepository(Invoice::class);
        $invoice = $repository->findOneBy(['id' => $invoiceData['id']]);

        if (!$invoice) {
            throw new HttpException(404, 'Unable to update invoice with id '.$invoiceData['id'].' as it does not already exist');
        }

        if ($invoice->getRecorded()) {
            throw new HttpException(400, 'Unable to update invoice with id '.$invoiceData['id'].' since it has been recorded.');
        }

        if (isset($invoiceData['name'])) {
            $invoice->setName($invoiceData['name']);
        }

        if (isset($invoiceData['description'])) {
            $invoice->setDescription($invoiceData['description']);
        }

        if (isset($invoiceData['customerAccountId'])) {
            $invoice->setCustomerAccountId($invoiceData['customerAccountId']);
        }

        if (isset($invoiceData['paidByAccount'])) {
            $invoice->setPaidByAccount($invoiceData['paidByAccount']);
        }

        if (isset($invoiceData['defaultPayToAccount'])) {
            $invoice->setDefaultPayToAccount($invoiceData['defaultPayToAccount']);
        }

        if (isset($invoiceData['defaultMaterialNumber'])) {
            $invoice->setDefaultMaterialNumber($invoiceData['defaultMaterialNumber']);
        }

        if (isset($invoiceData['periodFrom'])) {
            $from = $invoiceData['periodFrom'];
            $invoice->setPeriodFrom(new \DateTime($from));
        }

        if (isset($invoiceData['periodTo'])) {
            $to = $invoiceData['periodTo'];
            $invoice->setPeriodTo(new \DateTime($to));
        }

        if (isset($invoiceData['recorded'])) {
            $invoiceRecorded = $invoiceData['recorded'];
            $invoice->setRecorded($invoiceRecorded);
        }

        $this->entityManager->flush();

        return $this->getInvoiceArray($invoice, true);
    }

    /**
     * Delete specific invoice referenced by the given id.
     */
    public function deleteInvoice(int $invoiceId): void
    {
        if (empty($invoiceId) || !(int) $invoiceId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $repository = $this->entityManager->getRepository(Invoice::class);
        $invoice = $repository->findOneBy(['id' => $invoiceId]);

        if (!$invoice) {
            throw new HttpException(404, 'Invoice with id '.$invoiceId.' did not exist');
        }

        if ($invoice->getRecorded()) {
            throw new HttpException(400, 'Unable to delete invoice with id '.$invoiceId.' since it has been recorded.');
        }

        $this->entityManager->remove($invoice);
        $this->entityManager->flush();
    }

    /**
     * Get invoiceEntries for specific invoice.
     */
    public function getInvoiceEntries(int $invoiceId): array
    {
        if (!(int) $invoiceId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $repository = $this->entityManager->getRepository(Invoice::class);
        $invoice = $repository->findOneBy(['id' => $invoiceId]);

        $invoiceEntries = $invoice->getInvoiceEntries();

        $invoiceEntriesJson = [];

        foreach ($invoiceEntries as $invoiceEntry) {
            $invoiceEntry = $this->getInvoiceEntryArray($invoiceEntry);

            $invoiceEntriesJson[] = $invoiceEntry;
        }

        return $invoiceEntriesJson;
    }

    /**
     * Get all invoiceEntries.
     *
     * @return array
     */
    public function getAllInvoiceEntries(): array
    {
        $repository = $this->entityManager->getRepository(InvoiceEntry::class);
        $invoiceEntries = $repository->findAll();

        if (!$invoiceEntries) {
            return [];
        }

        $invoiceEntriesJson = [];

        foreach ($invoiceEntries as $invoiceEntry) {
            $invoiceEntriesJson[] = [
                'id' => $invoiceEntry->getId(),
                'invoiceId' => $invoiceEntry->getInvoice()->getId(),
                'description' => $invoiceEntry->getDescription(),
                'account' => $invoiceEntry->getAccount(),
                'product' => $invoiceEntry->getProduct(),
                'price' => $invoiceEntry->getPrice(),
                'amount' => $invoiceEntry->getAmount(),
            ];
        }

        return $invoiceEntriesJson;
    }

    /**
     * Get specific invoiceEntry by id.
     */
    public function getInvoiceEntry(int $invoiceEntryId): array
    {
        if (!(int) $invoiceEntryId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $repository = $this->entityManager->getRepository(InvoiceEntry::class);
        $invoiceEntry = $repository->findOneBy(['id' => $invoiceEntryId]);

        if (!$invoiceEntry) {
            throw new HttpException(404, 'InvoiceEntry with id '.$invoiceEntryId.' not found');
        }

        return $this->getInvoiceEntryArray($invoiceEntry);
    }

    /**
     * Get invoice entry as array.
     */
    private function getInvoiceEntryArray(InvoiceEntry $invoiceEntry): array
    {
        return [
            'id' => $invoiceEntry->getId(),
            'invoiceId' => $invoiceEntry->getInvoice()->getId(),
            'description' => $invoiceEntry->getDescription(),
            'account' => $invoiceEntry->getAccount(),
            'product' => $invoiceEntry->getProduct(),
            'entryType' => $invoiceEntry->getEntryType(),
            'materialNumber' => $invoiceEntry->getMaterialNumber(),
            'amount' => $invoiceEntry->getAmount(),
            'price' => $invoiceEntry->getPrice(),
            'worklogIds' => array_reduce($invoiceEntry->getWorklogs()->toArray(), function ($carry, Worklog $worklog) {
                $carry[$worklog->getWorklogId()] = true;

                return $carry;
            }, []),
            'expenseIds' => array_reduce($invoiceEntry->getExpenses()->toArray(), function ($carry, Expense $expense) {
                $carry[$expense->getExpenseId()] = true;

                return $carry;
            }, []),
        ];
    }

    /**
     * Post new invoiceEntry, creating a new entity referenced by the returned id.
     */
    public function postInvoiceEntry(array $invoiceEntryData): array
    {
        if (empty($invoiceEntryData['invoiceId']) || !(int) ($invoiceEntryData['invoiceId'])) {
            throw new HttpException(400, "Expected integer value for 'invoiceId' in request");
        }

        $invoiceRepository = $this->entityManager->getRepository(Invoice::class);
        /** @var Invoice $invoice */
        $invoice = $invoiceRepository->findOneBy(['id' => $invoiceEntryData['invoiceId']]);

        if (!$invoice) {
            throw new HttpException(404, 'Invoice with id '.$invoiceEntryData['invoiceId'].' not found');
        }

        if ($invoice->isRecorded()) {
            throw new HttpException(400, 'Invoice with id '.$invoiceEntryData['invoiceId'].' has been recorded');
        }

        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setInvoice($invoice);

        // Set defaults from Invoice.
        $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());
        $invoiceEntry->setAccount($invoice->getDefaultPayToAccount());

        $this->setInvoiceEntryValuesFromData($invoiceEntry, $invoiceEntryData);

        $this->entityManager->persist($invoiceEntry);
        $this->entityManager->flush();

        return $this->getInvoiceEntryArray($invoiceEntry);
    }

    /**
     * Set invoiceEntry from data array.
     */
    private function setInvoiceEntryValuesFromData(InvoiceEntry $invoiceEntry, array $invoiceEntryData): InvoiceEntry
    {
        if (isset($invoiceEntryData['entryType'])) {
            $invoiceEntry->setEntryType($invoiceEntryData['entryType']);
        }

        if (isset($invoiceEntryData['amount'])) {
            $invoiceEntry->setAmount($invoiceEntryData['amount']);
        }

        if (isset($invoiceEntryData['price'])) {
            $invoiceEntry->setPrice($invoiceEntryData['price']);
        }

        if (isset($invoiceEntryData['description'])) {
            $invoiceEntry->setDescription($invoiceEntryData['description']);
        }

        if (isset($invoiceEntryData['account'])) {
            $invoiceEntry->setAccount($invoiceEntryData['account']);
        }

        if (isset($invoiceEntryData['materialNumber'])) {
            $invoiceEntry->setMaterialNumber($invoiceEntryData['materialNumber']);
        }

        if (isset($invoiceEntryData['product'])) {
            $invoiceEntry->setProduct($invoiceEntryData['product']);
        }

        // If worklogIds has been changed.
        if (isset($invoiceEntryData['worklogIds'])) {
            $worklogs = $invoiceEntry->getWorklogs();
            $worklogIdsAlreadyAdded = array_reduce($worklogs->toArray(), function ($carry, Worklog $worklog) {
                $carry[] = $worklog->getWorklogId();

                return $carry;
            }, []);

            // Remove de-selected worklogs.
            foreach ($worklogs as $worklog) {
                if (!\in_array($worklog->getWorklogId(), $invoiceEntryData['worklogIds'])) {
                    $this->entityManager->remove($worklog);
                }
            }

            // Add not-added worklogs.
            foreach ($invoiceEntryData['worklogIds'] as $worklogId) {
                if (!\in_array($worklogId, $worklogIdsAlreadyAdded)) {
                    $worklog = $this->worklogRepository->findOneBy(['worklogId' => $worklogId]);

                    if (null === $worklog) {
                        $worklog = new Worklog();
                        $worklog->setWorklogId($worklogId);
                        $worklog->setInvoiceEntry($invoiceEntry);

                        $this->entityManager->persist($worklog);
                    } else {
                        if ($worklog->getInvoiceEntry()->getId() === $invoiceEntry->getId()) {
                            throw new HttpException('Used by other invoice entry.');
                        }
                    }
                }
            }
        }

        // If expenseIds has been changed.
        if (isset($invoiceEntryData['expenseIds'])) {
            $expenses = $invoiceEntry->getExpenses();
            $expenseIdsAlreadyAdded = array_reduce($expenses->toArray(), function ($carry, Expense $expense) {
                $carry[] = $expense->getExpenseId();

                return $carry;
            }, []);

            // Remove de-selected expenses.
            foreach ($expenses as $expense) {
                if (!\in_array($expense->getExpenseId(), $invoiceEntryData['expenseIds'])) {
                    $this->entityManager->remove($expense);
                }
            }

            // Add not-added expenses.
            foreach ($invoiceEntryData['expenseIds'] as $expenseId) {
                if (!\in_array($expenseId, $expenseIdsAlreadyAdded)) {
                    $expense = $this->expenseRepository->findOneBy(['expenseId' => $expenseId]);

                    if (null === $expense) {
                        $expense = new Expense();
                        $expense->setExpenseId($expenseId);
                        $expense->setInvoiceEntry($invoiceEntry);

                        $this->entityManager->persist($expense);
                    } else {
                        if ($expense->getInvoiceEntry()
                            ->getId() === $invoiceEntry->getId()) {
                            throw new HttpException('Used by other invoice entry.');
                        }
                    }
                }
            }
        }

        return $invoiceEntry;
    }

    /**
     * Put specific invoiceEntry, replacing the invoiceEntry referenced by the given id.
     */
    public function putInvoiceEntry(array $invoiceEntryData): array
    {
        if (empty($invoiceEntryData['id']) || !(int) ($invoiceEntryData['id'])) {
            throw new HttpException(400, "Expected integer value for 'id' in request");
        }

        $repository = $this->entityManager->getRepository(InvoiceEntry::class);
        $invoiceEntry = $repository->findOneBy(['id' => $invoiceEntryData['id']]);

        if (!$invoiceEntry) {
            throw new HttpException(404, 'Unable to update invoiceEntry with id '.$invoiceEntryData['id'].' as it does not already exist');
        }

        if ($invoiceEntry->getInvoice()->isRecorded()) {
            throw new HttpException(400, 'Unable to update invoiceEntry with id '.$invoiceEntryData['id'].' since the invoice it belongs to has been recorded.');
        }

        $invoiceEntry = $this->setInvoiceEntryValuesFromData($invoiceEntry, $invoiceEntryData);

        $this->entityManager->persist($invoiceEntry);
        $this->entityManager->flush();

        return $this->getInvoiceEntryArray($invoiceEntry);
    }

    /**
     * Delete specific invoice entry referenced by the given id.
     */
    public function deleteInvoiceEntry(int $invoiceEntryId): void
    {
        if (empty($invoiceEntryId) || !(int) $invoiceEntryId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $repository = $this->entityManager->getRepository(InvoiceEntry::class);
        $invoiceEntry = $repository->findOneBy(['id' => $invoiceEntryId]);

        if (!$invoiceEntry) {
            throw new HttpException(404, 'InvoiceEntry with id '.$invoiceEntryId.' did not exist');
        }

        if ($invoiceEntry->getInvoice()->isRecorded()) {
            throw new HttpException(400, 'Unable to delete invoiceEntry with id '.$invoiceEntryId.' since the invoice it belongs to has been recorded.');
        }

        $this->entityManager->remove($invoiceEntry);
        $this->entityManager->flush();
    }

    /**
     * Record an invoice.
     *
     * @throws InvoiceException
     */
    public function recordInvoice(int $invoiceId): array
    {
        $invoice = $this->entityManager->getRepository(Invoice::class)
            ->find($invoiceId);
        $invoice->setRecorded(true);
        $invoice->setRecordedDate(new \DateTime());

        $customerAccountId = $invoice->getCustomerAccountId();

        if (!$customerAccountId) {
            throw new InvoiceException('Customer account id not set.', 400);
        }

        try {
            $customerAccount = $this->apiService->getAccount($customerAccountId);
        } catch (\Exception $e) {
            if (404 === $e->getCode()) {
                throw new InvoiceException('Jira: Customer account not found', 404);
            }
        }

        if (!isset($customerAccount)) {
            throw new InvoiceException('Jira: Customer account does not exist', 400);
        }

        // Confirm that required fields are set for account.
        if (!isset($customerAccount->category)) {
            throw new InvoiceException('Jira: Category not set.', 400);
        }
        if (!isset($customerAccount->customer)) {
            throw new InvoiceException('Jira: Customer not set.', 400);
        }
        if (!isset($customerAccount->contact)) {
            throw new InvoiceException('Jira: Contact not set.', 400);
        }

        if (isset($customerAccount->category)) {
            $invoice->setLockedType($customerAccount->category->name);
            $invoice->setLockedSalesChannel($customerAccount->category->key);
        }

        if (isset($customerAccount->customer)) {
            $invoice->setLockedCustomerKey($customerAccount->customer->key);
        }

        if (isset($customerAccount->contact)) {
            $invoice->setLockedContactName($customerAccount->contact->name);
        }

        $invoice->setLockedAccountKey($customerAccount->key);

        // Set billed field in Jira for each worklog.
        foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
            if (true !== ($error = $this->checkInvoiceEntry($invoiceEntry))) {
                throw new InvoiceException('Invalid invoice entry '.$invoiceEntry->getId().': '.$error, 400);
            }

            foreach ($invoiceEntry->getWorklogs() as $worklog) {
                // @TODO: Record billed in Jira. Find a better way than below,
                // since this can involve multiple calls to Jira, if there
                // are many worklogs.
                /*
                $this->put('/rest/tempo-timesheets/4/worklogs/'.$worklog->getWorklogId(), [
                    'attributes' => [
                        '_Billed_' => [
                            'value' => true,
                        ],
                    ],
                ]);
                */

                $worklog->setIsBilled(true);
            }

            // @TODO: Record billed in Jira if possible.
            foreach ($invoiceEntry->getExpenses() as $expense) {
                $expense->setIsBilled(true);
            }
        }

        $this->entityManager->flush();

        return $this->getInvoiceArray($invoice);
    }

    private function checkInvoiceEntry(InvoiceEntry $invoiceEntry): bool|string
    {
        if (!$invoiceEntry->getEntryType()) {
            return 'entryType not set';
        }

        if (!$invoiceEntry->getAmount()) {
            return 'amount not set';
        }

        if (!$invoiceEntry->getPrice()) {
            return 'price not set';
        }

        if (!$invoiceEntry->getAccount()) {
            return 'account not set';
        }

        if (!$invoiceEntry->getMaterialNumber()) {
            return 'materialNumber not set';
        }

        if (!$invoiceEntry->getProduct()) {
            return 'product not set';
        }

        return true;
    }

    public function markInvoiceAsExported(int $invoiceId): void
    {
        $invoice = $this->invoiceRepository->findOneBy(['id' => $invoiceId]);

        if ($invoice) {
            $invoice->setExportedDate(new \DateTime());

            $this->entityManager->flush();
        }
    }

    /**
     * Export the selected invoices (by id) to csv.
     *
     * @param array $invoiceIds array of invoice ids that should be exported
     *
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function exportInvoicesToSpreadsheet(array $invoiceIds): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;

        foreach ($invoiceIds as $invoiceId) {
            $invoice = $this->invoiceRepository->findOneBy(['id' => $invoiceId]);

            if (null === $invoice) {
                continue;
            }

            if ($invoice->getRecorded()) {
                $internal = 'INTERN' === $invoice->getLockedType();
                $customerKey = $invoice->getLockedCustomerKey();
                $accountKey = $invoice->getLockedAccountKey();
                $salesChannel = $invoice->getLockedSalesChannel();
                $contactName = $invoice->getLockedContactName();
            } else {
                // If the invoice has not been recorded yet.
                $customerAccount = $this->getAccount($invoice->getCustomerAccountId());

                $internal = 'INTERN' === $customerAccount->category->name;
                $customerKey = $customerAccount->customer->key;
                $accountKey = $customerAccount->key;
                $salesChannel = $customerAccount->category->key;
                $contactName = $customerAccount->contact->name;
            }

            $today = new \DateTime();
            $todayString = $today->format('d.m.Y');
            $todayPlus30days = $today->add(new \DateInterval('P30D'));

            // Move ahead if the day is a saturday or sunday to ensure it is a bank day.
            // TODO: Handle holidays.
            $weekday = $todayPlus30days->format('N');
            if ('6' === $weekday) {
                $todayPlus30days->add(new \DateInterval('P2D'));
            } elseif ('7' === $weekday) {
                $todayPlus30days->add(new \DateInterval('P1D'));
            }

            $todayPlus30daysString = $todayPlus30days->format('d.m.Y');

            // Generate header line (H).
            // 1. "Linietype"
            $sheet->setCellValueByColumnAndRow(1, $row, 'H');
            // 2. "Ordregiver/Bestiller"
            $sheet->setCellValueByColumnAndRow(2, $row, str_pad($customerKey, 10, '0', \STR_PAD_LEFT));
            // 4. "Fakturadato"
            $sheet->setCellValueByColumnAndRow(4, $row, null !== $invoice->getRecordedDate() ? $invoice->getRecordedDate()->format('d.m.Y') : '');
            // 5. "Bilagsdato"
            $sheet->setCellValueByColumnAndRow(5, $row, $todayString);
            // 6. "Salgsorganisation"
            $sheet->setCellValueByColumnAndRow(6, $row, '0020');
            // 7. "Salgskanal"
            $sheet->setCellValueByColumnAndRow(7, $row, $salesChannel);
            // 8. "Division"
            $sheet->setCellValueByColumnAndRow(8, $row, '20');
            // 9. "Ordreart"
            $sheet->setCellValueByColumnAndRow(9, $row, $internal ? 'ZIRA' : 'ZRA');
            // 15. "Kunderef.ID"
            $sheet->setCellValueByColumnAndRow(15, $row, substr('Att: '.$contactName, 0, 35));
            // 16. "Toptekst, yderligere spec i det hvide felt på fakturaen"
            $sheet->setCellValueByColumnAndRow(16, $row, substr($invoice->getDescription(), 0, 500));
            // 17. "Leverandør"
            if ($internal) {
                $sheet->setCellValueByColumnAndRow(17, $row, str_pad($this->boundReceiverAccount, 10, '0', \STR_PAD_LEFT));
            }
            // 18. "EAN nr."
            if (!$internal && 13 === \strlen($accountKey)) {
                $sheet->setCellValueByColumnAndRow(18, $row, $accountKey);
            }

            // External invoices.
            if (!$internal) {
                // 38. Stiftelsesdato: dagsdato
                $sheet->setCellValueByColumnAndRow(24, $row, $todayString);
                // 39. Periode fra
                $sheet->setCellValueByColumnAndRow(25, $row, $invoice->getPeriodFrom() ? $invoice->getPeriodFrom()->format('d.m.Y') : '');
                // 40. Periode til
                $sheet->setCellValueByColumnAndRow(26, $row, $invoice->getPeriodTo() ? $invoice->getPeriodTo()->format('d.m.Y') : '');
                // 46. Fordringstype oprettelse/valg : KOCIVIL
                $sheet->setCellValueByColumnAndRow(32, $row, 'KOCIVIL');
                // 49. Forfaldsdato: dagsdato
                $sheet->setCellValueByColumnAndRow(35, $row, $todayString);
                // 50. Henstand til: dagsdato + 30 dage. NB det må ikke være før faktura forfald. Skal være en bank dag.
                $sheet->setCellValueByColumnAndRow(36, $row, $todayPlus30daysString);
            }

            ++$row;

            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                $materialNumber = $invoiceEntry->getMaterialNumber();
                $product = $invoiceEntry->getProduct();
                $amount = $invoiceEntry->getAmount();
                $price = $invoiceEntry->getPrice();
                $account = $invoiceEntry->getAccount();

                // Ignore lines that have missing data.
                if (!$materialNumber || !$product || !$amount || !$price || !$account) {
                    continue;
                }

                // Generate invoice lines (L).
                // 1. "Linietype"
                $sheet->setCellValueByColumnAndRow(1, $row, 'L');
                // 2. "Materiale (vare)nr.
                $sheet->setCellValueByColumnAndRow(2, $row, str_pad($materialNumber, 18, '0', \STR_PAD_LEFT));
                // 3. "Beskrivelse"
                $sheet->setCellValueByColumnAndRow(3, $row, substr($product, 0, 40));
                // 4. "Ordremængde"
                $sheet->setCellValueByColumnAndRow(4, $row, number_format($amount, 3, ',', ''));
                // 5. "Beløb pr. enhed"
                $sheet->setCellValueByColumnAndRow(5, $row, number_format($price, 2, ',', ''));
                // 6. "Priser fra SAP"
                $sheet->setCellValueByColumnAndRow(6, $row, 'NEJ');
                // 7. "PSP-element nr."
                $sheet->setCellValueByColumnAndRow(7, $row, $account);

                ++$row;
            }
        }

        return $spreadsheet;
    }

    /**
     * Get specific project by Jira project ID.
     */
    public function getProject(int $projectId)
    {
        if (!(int) $projectId) {
            throw new HttpException(400, 'Expected integer in request');
        }

        $result = $this->getProject($projectId);

        $repository = $this->entityManager->getRepository(Project::class);
        $project = $repository->findOneBy(['jiraId' => $projectId]);

        if (!$project) {
            $project = new Project();
            $this->entityManager->persist($project);
        }

        $project->setJiraId($result->id);
        $project->setJiraKey($result->key);
        $project->setName($result->name);
        $project->setUrl($result->self);
        $project->setAvatarUrl($result->avatarUrls->{'48x48'});

        $this->entityManager->flush();

        return $project;
    }

    /**
     * Get project worklogs with extra metadata.
     */
    public function getProjectWorklogsWithMetadata(int $projectId): mixed
    {
        $worklogs = $this->getProjectWorklogs($projectId);

        if (0 === \count($worklogs)) {
            return $worklogs;
        }

        $project = $this->getProject($projectId);
        $versions = $project->versions;
        $epics = $this->getProjectEpics($projectId);
        $accounts = $this->apiService->getAllAccounts();

        $epicNameCustomFieldId = $this->getCustomFieldId('Epic Name');

        foreach ($worklogs as $worklog) {
            $issue = $worklog->issue;

            // Enrich with epic name.
            if (!empty($issue->epicKey)) {
                foreach ($epics as $epic) {
                    if ($epic->key === $issue->epicKey) {
                        $issue->epicName = $epic->fields->{$epicNameCustomFieldId};
                        break;
                    }
                }
            }

            $issueVersions = [];
            $issueVersionKeys = array_values($issue->versions);

            foreach ($issueVersionKeys as $issueVersionKey) {
                foreach ($versions as $version) {
                    if ((int) $version->id === $issueVersionKey) {
                        $issueVersions[$issueVersionKey] = $version->name;
                    }
                }
            }

            $issue->versions = $issueVersions;

            // Enrich with account name.
            if (isset($issue->accountKey)) {
                foreach ($accounts as $account) {
                    if ($account->key === $issue->accountKey) {
                        $issue->accountName = $account->name;
                        break;
                    }
                }
            }

            $worklogEntity = $this->worklogRepository->findOneBy(['worklogId' => $worklog->tempoWorklogId]);

            if (null !== $worklogEntity) {
                $worklog->addedToInvoiceEntryId = $worklogEntity->getInvoiceEntry()->getId();

                $worklog->billed = $worklogEntity->isBilled();
            }
        }

        return $worklogs;
    }

    /**
     * Get project expenses.
     */
    public function getProjectExpenses(int $projectId): array
    {
        $allExpenses = $this->getExpenses();
        $issues = array_reduce($this->getProjectIssues($projectId), function ($carry, $issue) {
            $carry[$issue->id] = $issue;

            return $carry;
        }, []);

        $expenses = [];
        foreach ($allExpenses as $key => $expense) {
            if ('ISSUE' === $expense->scope->scopeType) {
                if (\in_array($expense->scope->scopeId, array_keys($issues))) {
                    $expense->issue = $issues[$expense->scope->scopeId];
                    $expenses[] = $expense;
                }
            }
        }

        return $expenses;
    }

    /**
     * Get project expenses with metadata about version, epic, etc.
     */
    public function getProjectExpensesWithMetadata(int $projectId): array
    {
        $expenses = $this->getProjectExpenses($projectId);

        if (0 === \count($expenses)) {
            return $expenses;
        }

        $epics = $this->getProjectEpics($projectId);

        $epicNameCustomFieldIdId = $this->getCustomFieldId('Epic Link');
        $epicNameCustomFieldId = $this->getCustomFieldId('Epic Name');
        $customFieldAccountKeyId = $this->getCustomFieldId('Account');

        foreach ($expenses as $expense) {
            foreach ($epics as $epic) {
                if ($epic->key === $expense->issue->fields->{$epicNameCustomFieldIdId}) {
                    $expense->issue->epicKey = $epic->key;
                    $expense->issue->epicName = $epic->fields->{$epicNameCustomFieldId};
                    break;
                }
            }

            if (isset($expense->issue->fields->{$customFieldAccountKeyId})) {
                $expense->issue->accountKey = $expense->issue->fields->{$customFieldAccountKeyId}->key;
                $expense->issue->accountName = $expense->issue->fields->{$customFieldAccountKeyId}->name;
            }

            $expense->issue->versions = array_reduce($expense->issue->fields->fixVersions, function ($carry, $version) {
                $carry->{$version->id} = $version->name;

                return $carry;
            }, (object) []);

            $expenseEntity = $this->expenseRepository->findOneBy(['expenseId' => $expense->id]);

            if (null !== $expenseEntity) {
                $expense->addedToInvoiceEntryId = $expenseEntity->getInvoiceEntry()->getId();

                $expense->billed = $expenseEntity->isBilled();
            }
        }

        return $expenses;
    }

    /**
     * Get epics for project.
     */
    public function getProjectEpics(int $projectId): array
    {
        return $this->getProjectIssues($projectId, 'Epic');
    }

    /**
     * Get project issues of a given issue type.
     */
    public function getProjectIssues($projectId, string $issueType = null, array $additionalJQL = null): array
    {
        $issues = [];

        $jql = 'project='.$projectId;

        if (null !== $issueType) {
            $jql = $jql.' and issuetype='.$issueType;
        }

        if (null !== $additionalJQL) {
            foreach ($additionalJQL as $addJql) {
                $jql = $jql.' and ('.$addJql.')';
            }
        }

        $startAt = 0;
        while (true) {
            $result = $this->get('/rest/api/2/search', [
                'jql' => $jql,
                'maxResults' => 50,
                'startAt' => $startAt,
            ]);
            $issues = array_merge($issues, $result->issues);

            $startAt = $startAt + 50;

            if ($startAt > $result->total) {
                break;
            }
        }

        return $issues;
    }

    /**
     * Get accounts for a given project id.
     */
    public function getProjectAccounts(int $projectId): mixed
    {
        $cacheKey = 'project_accounts_'.$projectId;
        if ($this->cache->contains($cacheKey)) {
            return $this->cache->fetch($cacheKey);
        }

        $accountIds = $this->apiService->getAccountIdsByProject($projectId);
        $accounts = [];
        foreach ($accountIds as $accountId) {
            $accounts[$accountId] = $this->apiService->getAccount($accountId);
            $accounts[$accountId]->defaultPrice = $this->getAccountDefaultPrice($accountId);
        }

        // Cache result for one day.
        $this->cache->save($cacheKey, $accounts, 60 * 60 * 24);

        return $accounts;
    }

    /**
     * Get to accounts.
     */
    public function getToAccounts(): mixed
    {
        $cacheKey = 'to_accounts';
        if ($this->cache->contains($cacheKey)) {
            return $this->cache->fetch($cacheKey);
        }

        $toAccounts = [];
        $allAccounts = $this->apiService->getAllAccounts();

        foreach ($allAccounts as $account) {
            if ('INTERN' === $account->category->name) {
                if (str_starts_with($account->key, 'XG') || str_starts_with($account->key, 'XD')) {
                    $toAccounts[$account->key] = $account;
                }
            }
        }

        // Cache result for one day.
        $this->cache->save($cacheKey, $toAccounts, 60 * 60 * 24);

        return $toAccounts;
    }

    /**
     * Clear cache entries.
     */
    public function clearCache(): bool
    {
        return $this->cache->flushAll();
    }

    /**
     * Get default price from account.
     */
    public function getAccountDefaultPrice(int $accountId): int|null
    {
        $rateTable = $this->apiService->getRateTableByAccount($accountId);

        foreach ($rateTable->rates as $rate) {
            if ('DEFAULT_RATE' === $rate->link->type) {
                return $rate->amount;
            }
        }

        return null;
    }
}
