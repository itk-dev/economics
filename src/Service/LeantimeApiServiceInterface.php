<?php

namespace App\Service;

use App\Model\Planning\PlanningData;
use App\Model\SprintReport\SprintReportData;

interface LeantimeApiServiceInterface
{
    public function getEndpoints(): array;

    public function getAllProjects(): mixed;

    public function getProject($key): mixed;

    public function getMilestone($key): mixed;

    public function getProjectMilestones($key): mixed;

    public function getAllSprints(): array;

    public function getTicketsInSprint(string $sprintId): array;

    public function getPlanningData(): PlanningData;

    public function getTimesheetsForTicket($ticketId): mixed;

    public function getSprintReportData(string $projectId, string $milestoneId): SprintReportData;
}
