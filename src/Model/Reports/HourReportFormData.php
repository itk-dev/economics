<?php

namespace App\Model\Reports;

use App\Entity\Project;
use App\Entity\Version;

class HourReportFormData
{
    public Project $project;
    public Version $version;
    public \DateTimeInterface $fromDate;
    public \DateTimeInterface $toDate;
}
