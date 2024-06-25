<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Entity\Version;

class HourReportFormData
{
    public DataProvider $dataProvider;
    public Project $project;
    public Version $version;
    public \DateTimeInterface $fromDate;
    public \DateTimeInterface $toDate;
}
