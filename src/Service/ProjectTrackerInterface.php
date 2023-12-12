<?php

namespace App\Service;

use App\Model\Planning\PlanningData;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportProject;
use App\Model\SprintReport\SprintReportProjects;
use App\Model\SprintReport\SprintReportVersions;

interface ProjectTrackerInterface
{
    public function getPlanningData(): PlanningData;

    public function getSprintReportData(string $projectId, string $versionId): SprintReportData;

    public function getAllProjectsV2(): SprintReportProjects;

    public function getProjectV2(string $projectId): SprintReportProject;

    public function getProjectVersions(string $projectId): SprintReportVersions;
}
