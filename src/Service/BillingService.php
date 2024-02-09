<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Exception\EconomicsException;
use App\Exception\InvoiceAlreadyOnRecordException;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class BillingService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceEntryRepository $invoiceEntryRepository,
        private readonly TranslatorInterface $translator,
        private readonly string $invoiceSupplierAccount,
    ) {
    }

    /**
     * Update total price for invoice entry by multiplying amount with price.
     */
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

    /**
     * Update total price for invoice by summing invoice entry total prices.
     */
    public function updateInvoiceTotalPrice(Invoice $invoice): void
    {
        $totalPrice = 0;

        foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
            $totalPrice += ($invoiceEntry->getTotalPrice() ?? 0);
        }

        $invoice->setTotalPrice($totalPrice);

        $this->invoiceRepository->save($invoice, true);
    }

    /**
     * @throws InvoiceAlreadyOnRecordException
     * @throws EconomicsException
     */
    public function recordInvoice(Invoice $invoice, bool $flush = true): void
    {
        if ($invoice->isRecorded()) {
            throw new InvoiceAlreadyOnRecordException('Invoice is already on record.');
        }

        // Make sure client is set.
        $errors = $this->getInvoiceRecordableErrors($invoice);

        if (!empty($errors)) {
            throw new EconomicsException($this->translator->trans('exception.billing_cannot_put_invoice_on_record_errors_found', ['%invoiceName%' => $invoice->getName(), '%invoiceId%' => $invoice->getId(), '%errors%' => json_encode($errors)]));
        }

        $client = $invoice->getClient();

        if (is_null($client)) {
            throw new EconomicsException($this->translator->trans('exception.invoice_client_must_be_set'));
        }

        // Lock client values.
        // The locked type is handled this way to be backwards compatible with Jira Economics.
        $invoice->setLockedType(ClientTypeEnum::INTERNAL == $client->getType() ? 'INTERN' : 'EKSTERN');
        $invoice->setLockedCustomerKey($client->getCustomerKey());
        $invoice->setLockedContactName($client->getContact());
        $invoice->setLockedEan($client->getEan() ?? '');

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

        $this->invoiceRepository->save($invoice, $flush);
    }

    // TODO: Replace with exceptions.
    public function getInvoiceRecordableErrors(Invoice $invoice): array
    {
        $errors = [];

        $client = $invoice->getClient();

        foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
            if (0 == $invoiceEntry->getAmount()) {
                $errors[] = $this->translator->trans('invoice_recordable.error_empty_invoice_entry', ['%invoiceEntryId%' => $invoiceEntry->getId()]);
            }
        }

        if (is_null($client)) {
            $errors[] = $this->translator->trans('invoice_recordable.error_no_client');
        } else {
            if (!$client->getContact()) {
                $errors[] = $this->translator->trans('invoice_recordable.error_no_contact');
            }

            if (!$client->getType()) {
                $errors[] = $this->translator->trans('invoice_recordable.error_no_type');
            }
        }

        return $errors;
    }

    /**
     * Create a spreadsheet response from an array of invoice ids.
     *
     * @param array $ids array of invoice ids
     *
     * @throws EconomicsException
     */
    public function generateSpreadsheetCsvResponse(array $ids): Response
    {
        try {
            $spreadsheet = $this->exportInvoicesToSpreadsheet($ids);

            /** @var Csv $writer */
            $writer = IOFactory::createWriter($spreadsheet, 'Csv');
            $writer->setDelimiter(';');
            $writer->setEnclosure('');
            $writer->setLineEnding("\r\n");
            $writer->setSheetIndex(0);

            $csvOutput = $this->getSpreadsheetOutputAsString($writer);

            // Change encoding to Windows-1252.
            $csvOutputEncoded = mb_convert_encoding($csvOutput, 'Windows-1252');

            $response = new Response($csvOutputEncoded);
            $filename = 'invoices-'.date('d-m-Y').'.csv';

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;
        } catch (PhpSpreadsheetException) {
            throw new EconomicsException($this->translator->trans('exception.invoices_export_failure'), 400);
        }
    }

    /**
     * Create spreadsheet html from an array of invoice ids.
     *
     * @throws EconomicsException
     */
    public function generateSpreadsheetHtml(array $ids): bool|string
    {
        try {
            $spreadsheet = $this->exportInvoicesToSpreadsheet($ids);

            $writer = IOFactory::createWriter($spreadsheet, 'Html');

            $html = $this->getSpreadsheetOutputAsString($writer);

            if (empty($html)) {
                $html = '<html lang="da" />';
            }

            // Extract body content.
            $d = new \DOMDocument();
            $mock = new \DOMDocument();
            $d->loadHTML($html);
            /** @var \DOMNode $body */
            $body = $d->getElementsByTagName('div')->item(0);

            foreach ($body->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    if ('table' == $child->tagName) {
                        $child->setAttribute('class', 'table table-export');
                    }
                }
                $mock->appendChild($mock->importNode($child, true));
            }

            $html = $mock->saveHTML();

            if (!$html) {
                throw new EconomicsException($this->translator->trans('exception.invoices_export_failure_could_not_generate_html'), 400);
            }

            return $html;
        } catch (PhpSpreadsheetException) {
            throw new EconomicsException($this->translator->trans('exception.invoices_export_failure'), 400);
        }
    }

    /**
     * Export the selected invoices (by id) to csv.
     *
     * @param array $invoiceIds array of invoice ids that should be exported
     *
     * @return Spreadsheet
     *
     * @throws EconomicsException
     */
    private function exportInvoicesToSpreadsheet(array $invoiceIds): Spreadsheet
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
                $accountKey = $invoice->getLockedEan();
                $contactName = $invoice->getLockedContactName();
            } else {
                // If the invoice has not been recorded yet.
                $client = $invoice->getClient();

                if (is_null($client)) {
                    throw new EconomicsException('Client cannot be null.', 400);
                }

                $internal = ClientTypeEnum::INTERNAL === $client->getType();
                $customerKey = $client->getCustomerKey();
                $accountKey = $client->getEan() ?? '';
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
            $sheet->setCellValue([7, $row], $internal ? 10 : 20);
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
                $sheet->setCellValue([17, $row], str_pad($this->invoiceSupplierAccount, 10, '0', \STR_PAD_LEFT));
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
     * @throws PhpSpreadsheetException
     */
    private function getSpreadsheetOutputAsString(IWriter $writer): string
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
