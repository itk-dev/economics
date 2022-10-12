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
    public function createJiraProject(array $data): ?string;

}
