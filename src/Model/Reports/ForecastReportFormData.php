<?php

namespace App\Model\Reports;

class ForecastReportFormData implements HasGroupFilter
{
    use HasGroupFilterTrait;

    public \DateTimeInterface $dateFrom;
    public \DateTimeInterface $dateTo;
}
