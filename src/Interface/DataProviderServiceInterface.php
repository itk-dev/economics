<?php

namespace App\Interface;

use App\Model\Invoices\ClientData;
use App\Model\Invoices\ProjectData;
use App\Model\Planning\PlanningData;

interface DataProviderServiceInterface
{
    public function getPlanningData(): PlanningData;

    /**
     * @return array<ProjectData>
     */
    public function getAllProjectData(): array;

    /**
     * @return array<ClientData>
     */
    public function getClientDataForProject(string $projectId): array;
}
