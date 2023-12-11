<?php

namespace App\Service;
use App\Model\Planning\PlanningData;

interface LeantimeApiServiceInterface
{
    public function getEndpoints(): array;

    public function getAllSprints(): array;

    public function getTicketsInSprint(string $sprintId): array;

    public function getPlanningData(): PlanningData;

}
