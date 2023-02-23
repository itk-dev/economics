<?php

namespace App\Service;

use App\Service\ProjectTracker\ApiServiceInterface;

class UserService
{
    public function __construct(
        private readonly ApiServiceInterface $apiService,
    ) {
    }

    public function anonymizeUser(string $key): void
    {
        $this->apiService->anonymizeUser($key);
    }

    public function findUnwantedUsers(callable $progressCallback): array
    {
        $unwantedUsers = [];

        $allUserKeys = $this->apiService->getAllUserKeys();

        foreach ($allUserKeys as $index => $userKey) {
            $user = $this->apiService->getUser($userKey);

            if (!$user->inGroups && !$user->hasOpenIssues && !$user->hasApplicationRoles) {
                $unwantedUsers[] = $user;
            }

            $progressCallback($index, count($allUserKeys));
        }

        return $unwantedUsers;
    }
}
