<?php

namespace App\Model\SprintReport;

use App\Entity\DataProvider;

class SprintReportFormData
{
    public DataProvider $dataProvider;
    public string $projectId;
    public string $versionId;
}
