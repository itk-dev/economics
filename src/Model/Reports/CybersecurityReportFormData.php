<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;
use App\Entity\Version;

class CybersecurityReportFormData
{
    public DataProvider $dataProvider;
    public Version $version;
    public \DateTimeInterface $fromDate;
    public \DateTimeInterface $toDate;
}
