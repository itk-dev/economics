<?php

namespace App\Enum;

enum InvoiceEntryTypeEnum: string
{
    case MANUAL = 'manual';
    case PRODUCT = 'product';
    case WORKLOG = 'worklog';
}
