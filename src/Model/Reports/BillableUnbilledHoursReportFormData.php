<?php

namespace App\Model\Reports;

class BillableUnbilledHoursReportFormData implements HasGroupFilter
{
    use HasGroupFilterTrait;

    public int $year;
    public ?int $quarter;
}
