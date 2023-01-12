<?php

namespace App\Model\SprintReport;

class SprintReportFormData
{
    private string $projectId;
    private string $versionId;
    private string $data;

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function setProjectId(string $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }
}
