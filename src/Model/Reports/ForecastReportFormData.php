<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;

class ForecastReportFormData
{
    public DataProvider $dataProvider;
    public \DateTimeInterface $dateFrom;
    public \DateTimeInterface $dateTo;
}
