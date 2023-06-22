<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Repository\AccountRepository;
use App\Repository\ClientRepository;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class BillingService
{
    public function __construct(
        private readonly ApiServiceInterface $apiService,
        private readonly ProjectRepository $projectRepository,
        private readonly ClientRepository $clientRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceEntryRepository $invoiceEntryRepository,
        private readonly VersionRepository $versionRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly IssueRepository $issueRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly AccountRepository $accountRepository,
        private readonly string $receiverAccount,
    ) {
    }

    /**
     * Migrate from invoice.customer_account_id to invoice.client.
     */
    public function migrateCustomers(): void
    {
        $invoices = $this->invoiceRepository->findAll();

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $customerAccountId = $invoice->getCustomerAccountId();

            if (null == $invoice->getClient() && null !== $customerAccountId) {
                $client = $this->clientRepository->findOneBy(['projectTrackerId' => $invoice->getCustomerAccountId()]);

                if (null !== $client) {
                    $invoice->setClient($client);
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @throws \Exception
     */
    public function syncIssuesForProject(string $projectId, callable $progressCallback = null): void
    {
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw new \Exception('Project not found');
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null === $projectTrackerId) {
            throw new \Exception('ProjectTrackerId not set');
        }

        $issueData = $this->apiService->getIssuesDataForProject($projectTrackerId);
        $issuesProcessed = 0;

        foreach ($issueData as $issueDatum) {
            $issue = $this->issueRepository->findOneBy(['projectTrackerId' => $issueDatum->projectTrackerId]);

            if (!$issue) {
                $issue = new Issue();

                $this->entityManager->persist($issue);
            }

            $issue->setName($issueDatum->name);
            $issue->setAccountId($issueDatum->accountId);
            $issue->setAccountKey($issueDatum->accountKey);
            $issue->setEpicKey($issueDatum->epicKey);
            $issue->setEpicName($issueDatum->epicName);
            $issue->setProject($project);
            $issue->setProjectTrackerId($issueDatum->projectTrackerId);
            $issue->setProjectTrackerKey($issueDatum->projectTrackerKey);
            $issue->setResolutionDate($issueDatum->resolutionDate);
            $issue->setStatus($issueDatum->status);

            if (null == $issue->getSource()) {
                $issue->setSource($this->apiService->getProjectTrackerIdentifier());
            }

            foreach ($issueDatum->versions as $versionData) {
                $version = $this->versionRepository->findOneBy(['projectTrackerId' => $versionData->projectTrackerId]);

                if (null !== $version) {
                    $issue->addVersion($version);
                }
            }

            if (null !== $progressCallback) {
                $progressCallback($issuesProcessed, count($issueData));
                ++$issuesProcessed;
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @throws \Exception
     */
    public function syncWorklogsForProject(string $projectId, callable $progressCallback = null): void
    {
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw new \Exception('Project not found');
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null === $projectTrackerId) {
            throw new \Exception('ProjectTrackerId not set');
        }

        $worklogData = $this->apiService->getWorklogDataForProject($projectTrackerId);
        $worklogsAdded = 0;

        foreach ($worklogData as $worklogDatum) {
            $worklog = $this->worklogRepository->findOneBy(['worklogId' => $worklogDatum->projectTrackerId]);

            if (!$worklog) {
                $worklog = new Worklog();

                $this->entityManager->persist($worklog);
            }

            $worklog->setWorklogId($worklogDatum->projectTrackerId);
            $worklog->setDescription($worklogDatum->comment);
            $worklog->setWorker($worklogDatum->worker);
            $worklog->setStarted($worklogDatum->started);
            $worklog->setProjectTrackerIssueId($worklogDatum->projectTrackerIssueId);
            $worklog->setTimeSpentSeconds($worklogDatum->timeSpentSeconds);

            if (null !== $worklog->getProjectTrackerIssueId()) {
                $issue = $this->issueRepository->findOneBy(['projectTrackerId' => $worklog->getProjectTrackerIssueId()]);
                $worklog->setIssue($issue);
            }

            if (!$worklog->isBilled() && $worklogDatum->projectTrackerIsBilled) {
                $worklog->setIsBilled(true);
                $worklog->setBilledSeconds($worklogDatum->timeSpentSeconds);
            }

            if (null == $worklog->getSource()) {
                $worklog->setSource($this->apiService->getProjectTrackerIdentifier());
            }

            $project->addWorklog($worklog);

            if (null !== $progressCallback) {
                $progressCallback($worklogsAdded, count($worklogData));

                ++$worklogsAdded;
            }
        }

        $this->entityManager->flush();
    }

    public function updateInvoiceEntryTotalPrice(InvoiceEntry $invoiceEntry): void
    {
        if (InvoiceEntryTypeEnum::WORKLOG === $invoiceEntry->getEntryType()) {
            $amountSeconds = 0;

            foreach ($invoiceEntry->getWorklogs() as $worklog) {
                $amountSeconds += $worklog->getTimeSpentSeconds() ?? 0;
            }

            $invoiceEntry->setAmount($amountSeconds / 3600);
        }

        $invoiceEntry->setTotalPrice(($invoiceEntry->getPrice() ?? 0) * $invoiceEntry->getAmount());
        $this->invoiceEntryRepository->save($invoiceEntry, true);

        $invoice = $invoiceEntry->getInvoice();
        if (!is_null($invoice)) {
            $this->updateInvoiceTotalPrice($invoice);
        }
    }

    public function updateInvoiceTotalPrice(Invoice $invoice): void
    {
        $totalPrice = 0;

        foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
            $totalPrice += ($invoiceEntry->getTotalPrice() ?? 0);
        }

        $invoice->setTotalPrice($totalPrice);

        $this->invoiceRepository->save($invoice, true);
    }

    public function syncAccounts(callable $progressCallback): void
    {
        $projectTrackerIdentifier = $this->apiService->getProjectTrackerIdentifier();

        // Get all accounts from ApiService.
        $allAccountData = $this->apiService->getAllAccountData();

        foreach ($allAccountData as $index => $accountDatum) {
            $account = $this->accountRepository->findOneBy(['projectTrackerId' => $accountDatum->projectTrackerId, 'source' => $projectTrackerIdentifier]);

            if (!$account) {
                $account = new Account();
                $account->setSource($projectTrackerIdentifier);
                $account->setProjectTrackerId($accountDatum->projectTrackerId);

                $this->entityManager->persist($account);
            }

            $account->setName($accountDatum->name);
            $account->setValue($accountDatum->value);
            $account->setStatus($accountDatum->status);
            $account->setCategory($accountDatum->category);

            $this->entityManager->flush();
            $this->entityManager->clear();

            $progressCallback($index, count($allAccountData));
        }

        $this->entityManager->flush();
    }

    public function syncProjects(callable $progressCallback): void
    {
        // Get all projects from ApiService.
        $allProjectData = $this->apiService->getAllProjectData();

        foreach ($allProjectData as $index => $projectDatum) {
            $project = $this->projectRepository->findOneBy(['projectTrackerId' => $projectDatum->projectTrackerId]);

            if (!$project) {
                $project = new Project();
                $this->entityManager->persist($project);
            }

            $project->setName($projectDatum->name);
            $project->setProjectTrackerId($projectDatum->projectTrackerId);
            $project->setProjectTrackerKey($projectDatum->projectTrackerKey);
            $project->setProjectTrackerProjectUrl($projectDatum->projectTrackerProjectUrl);

            foreach ($projectDatum->versions as $versionData) {
                $version = $this->versionRepository->findOneBy(['projectTrackerId' => $versionData->projectTrackerId]);

                if (!$version) {
                    $version = new Version();
                    $this->entityManager->persist($version);
                }

                $version->setName($versionData->name);
                $version->setProjectTrackerId($versionData->projectTrackerId);
                $version->setProject($project);
            }

            $projectClientData = $this->apiService->getClientDataForProject($projectDatum->projectTrackerId);

            foreach ($projectClientData as $clientData) {
                $client = $this->clientRepository->findOneBy(['projectTrackerId' => $clientData->projectTrackerId]);

                if (!$client) {
                    $client = new Client();
                    $client->setProjectTrackerId($clientData->projectTrackerId);
                    $this->entityManager->persist($client);
                }

                $client->setName($clientData->name);
                $client->setContact($clientData->contact);
                $client->setAccount($clientData->account);
                $client->setType($clientData->type);
                $client->setPsp($clientData->psp);
                $client->setEan($clientData->ean);
                $client->setStandardPrice($clientData->standardPrice);
                $client->setCustomerKey($clientData->customerKey);
                $client->setSalesChannel($clientData->salesChannel);

                if (!$client->getProjects()->contains($client)) {
                    $client->addProject($project);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            $progressCallback($index, count($allProjectData));
        }

        $this->entityManager->flush();
    }

    /**
     * @throws \Exception
     */
    public function recordInvoice(Invoice $invoice): void
    {
        // Make sure client is set.
        $errors = $this->getInvoiceRecordableErrors($invoice);

        if ($invoice->isRecorded()) {
            throw new \Exception('Already recorded.');
        }

        if (!empty($errors)) {
            throw new \Exception('Cannot record invoices. Errors not handled.');
        }

        $client = $invoice->getClient();

        if (is_null($client)) {
            throw new \Exception('Client must be set');
        }

        // Lock client values.
        // The locked type is handled this way to be backwards compatible with Jira Economics.
        $invoice->setLockedType(ClientTypeEnum::INTERNAL == $client->getType() ? 'INTERN' : 'EKSTERN');
        $invoice->setLockedSalesChannel($client->getSalesChannel());
        $invoice->setLockedCustomerKey($client->getCustomerKey());
        $invoice->setLockedContactName($client->getContact());
        $invoice->setLockedAccountKey($client->getAccount());

        $invoice->setRecorded(true);
        $invoice->setRecordedDate(new \DateTime());

        foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
            if (InvoiceEntryTypeEnum::WORKLOG === $invoiceEntry->getEntryType()) {
                foreach ($invoiceEntry->getWorklogs() as $worklog) {
                    $worklog->setIsBilled(true);
                    $worklog->setBilledSeconds($worklog->getTimeSpentSeconds());
                }
            }
        }

        $this->invoiceRepository->save($invoice, true);
    }

    // TODO: Replace with exceptions.
    public function getInvoiceRecordableErrors(Invoice $invoice): array
    {
        $errors = [];

        $client = $invoice->getClient();

        if (is_null($client)) {
            $errors[] = $this->translator->trans('invoice_recordable.error_no_client');

            return $errors;
        }

        if (!$client->getAccount()) {
            $errors[] = $this->translator->trans('invoice_recordable.error_no_account');
        }

        if (!$client->getContact()) {
            $errors[] = $this->translator->trans('invoice_recordable.error_no_contact');
        }

        if (!$client->getType()) {
            $errors[] = $this->translator->trans('invoice_recordable.error_no_type');
        }

        return $errors;
    }

    /**
     * Export the selected invoices (by id) to csv.
     *
     * @param array $invoiceIds array of invoice ids that should be exported
     *
     * @return Spreadsheet
     *
     * @throws \Exception
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

            if ($invoice->isRecorded()) {
                $internal = 'INTERN' === $invoice->getLockedType();
                $customerKey = $invoice->getLockedCustomerKey();
                $accountKey = $invoice->getLockedAccountKey();
                $salesChannel = $invoice->getLockedSalesChannel();
                $contactName = $invoice->getLockedContactName();
            } else {
                // If the invoice has not been recorded yet.
                $client = $invoice->getClient();

                if (is_null($client)) {
                    throw new \Exception('Client cannot be null.');
                }

                $internal = ClientTypeEnum::INTERNAL === $client->getType();
                $customerKey = $client->getCustomerKey();
                $accountKey = $client->getAccount();
                $salesChannel = $client->getSalesChannel();
                $contactName = $client->getContact();
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
            $sheet->setCellValue([1, $row], 'H');
            // 2. "Ordregiver/Bestiller"
            $sheet->setCellValue([2, $row], str_pad($customerKey ?? '', 10, '0', \STR_PAD_LEFT));
            // 4. "Fakturadato"
            $recordedDate = $invoice->getRecordedDate();
            $sheet->setCellValue([4, $row], null !== $recordedDate ? $recordedDate->format('d.m.Y') : '');
            // 5. "Bilagsdato"
            $sheet->setCellValue([5, $row], $todayString);
            // 6. "Salgsorganisation"
            $sheet->setCellValue([6, $row], '0020');
            // 7. "Salgskanal"
            $sheet->setCellValue([7, $row], $salesChannel);
            // 8. "Division"
            $sheet->setCellValue([8, $row], '20');
            // 9. "Ordreart"
            $sheet->setCellValue([9, $row], $internal ? 'ZIRA' : 'ZRA');
            // 15. "Kunderef.ID"
            $sheet->setCellValue([15, $row], substr('Att: '.$contactName, 0, 35));
            // 16. "Toptekst, yderligere spec i det hvide felt på fakturaen"
            $description = $invoice->getDescription() ?? '';
            $sheet->setCellValue([16, $row], substr($description, 0, 500));
            // 17. "Leverandør"
            if ($internal) {
                $sheet->setCellValue([17, $row], str_pad($this->receiverAccount, 10, '0', \STR_PAD_LEFT));
            }
            // 18. "EAN nr."
            if (!$internal && 13 === \strlen($accountKey ?? '')) {
                $sheet->setCellValue([18, $row], $accountKey);
            }

            // External invoices.
            if (!$internal) {
                // 38. Stiftelsesdato: dagsdato
                $sheet->setCellValue([24, $row], $todayString);
                // 39. Periode fra
                $periodFrom = $invoice->getPeriodFrom();
                $sheet->setCellValue([25, $row], null !== $periodFrom ? $periodFrom->format('d.m.Y') : '');
                // 40. Periode til
                $periodTo = $invoice->getPeriodTo();
                $sheet->setCellValue([26, $row], null !== $periodTo ? $periodTo->format('d.m.Y') : '');
                // 46. Fordringstype oprettelse/valg : KOCIVIL
                $sheet->setCellValue([32, $row], 'KOCIVIL');
                // 49. Forfaldsdato: dagsdato
                $sheet->setCellValue([35, $row], $todayString);
                // 50. Henstand til: dagsdato + 30 dage. NB det må ikke være før faktura forfald. Skal være en bank dag.
                $sheet->setCellValue([36, $row], $todayPlus30daysString);
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
                $sheet->setCellValue([1, $row], 'L');
                // 2. "Materiale (vare)nr.
                $sheet->setCellValue([2, $row], str_pad($materialNumber->value, 18, '0', \STR_PAD_LEFT));
                // 3. "Beskrivelse"
                $sheet->setCellValue([3, $row], substr($product, 0, 40));
                // 4. "Ordremængde"
                $sheet->setCellValue([4, $row], number_format($amount, 3, ',', ''));
                // 5. "Beløb pr. enhed"
                $sheet->setCellValue([5, $row], number_format($price, 2, ',', ''));
                // 6. "Priser fra SAP"
                $sheet->setCellValue([6, $row], 'NEJ');
                // 7. "PSP-element nr."
                $sheet->setCellValue([7, $row], $account);

                ++$row;
            }
        }

        return $spreadsheet;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getSpreadsheetOutputAsString(IWriter $writer): string
    {
        $filesystem = new Filesystem();
        $tempFilename = $filesystem->tempnam(sys_get_temp_dir(), 'export_');

        // Save to temp file.
        $writer->save($tempFilename);

        $output = file_get_contents($tempFilename);

        $filesystem->remove($tempFilename);

        if (!$output) {
            return '';
        }

        return $output;
    }
}
