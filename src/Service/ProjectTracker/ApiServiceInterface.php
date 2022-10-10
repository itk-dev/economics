<?php

namespace App\Service\ProjectTracker;

interface ApiServiceInterface
{
    public function getAllAccounts(): mixed;

    public function getAllProjectCategories(): mixed;

    public function getAllCustomers(): mixed;

    public function getCurrentUserPermissions(): mixed;

    public function getPermissionsList(): array;
}
