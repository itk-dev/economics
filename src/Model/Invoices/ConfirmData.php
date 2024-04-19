<?php

namespace App\Model\Invoices;

final class ConfirmData
{
    public const INVOICE_RECORD_NO = 'INVOICE_RECORD_NO';
    public const INVOICE_RECORD_YES = 'INVOICE_RECORD_YES';
    public const INVOICE_RECORD_YES_NO_COST = 'INVOICE_RECORD_YES_NO_COST';

    public string $confirmation = self::INVOICE_RECORD_NO;
}
