<?php

namespace App\Enum;

enum InvoiceEntryTypeEnum: string
{
    case MANUAL = 'manual';
    case WORKLOG = 'worklog';
}
