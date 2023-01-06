<?php

namespace App\Service\ProjectTracker;

interface ApiServiceInterface
{
    public function getAllAccounts(): mixed;

    public function getAllCustomers(): mixed;

    public function getAllProjectCategories(): mixed;

    public function getAllProjects(): mixed;

    public function getCurrentUserPermissions(): mixed;

    public function getPermissionsList(): array;

    public function getProject($key): mixed;

    public function createProject(array $data): ?string;

    public function createTimeTrackerCustomer(string $name, string $key): mixed;

    public function getTimeTrackerAccount(string $key): mixed;

    public function createTimeTrackerAccount(string $name, string $key, string $customerKey, string $contactUsername): mixed;

    public function addProjectToTimeTrackerAccount(mixed $project, mixed $account): void;

    public function createProjectBoard(string $type, mixed $project): void;

    public function getAccount(int $accountId): mixed;

    public function getRateTableByAccount(int $accountId): mixed;

    public function getAccountIdsByProject(int $projectId): mixed;

    public function getAllBoards(): mixed;

    public function getAllSprints(string $boardId): array;

    public function getIssuesInSprint(string $boardId, string $sprintId): array;
}
