<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;

class CybersecurityReportFormData
{
    public DataProvider $dataProvider;
    public string $versionTitle;
    public \DateTimeInterface $fromDate;
    public \DateTimeInterface $toDate;
}
