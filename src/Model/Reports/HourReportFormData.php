<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;

class HourReportFormData
{
    public DataProvider $dataProvider;
    public string $projectId;
    public string $versionId;
    public \DateTimeInterface $fromDate;
    public \DateTimeInterface $toDate;
}
