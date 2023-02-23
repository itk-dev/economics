<?php

namespace App\Model\PruneUsers;

class UserData
{
    public readonly string $name;
    public readonly string $key;
    public readonly string $displayName;
    public readonly string $email;
    public readonly bool $deleted;
    public readonly bool $active;
    public readonly bool $inGroups;
    public readonly bool $hasApplicationRoles;
    public readonly bool $hasOpenIssues;

    public function __construct(string $name, string $key, string $displayName, string $email, bool $deleted, bool $active, bool $inGroups, bool $hasApplicationRoles, bool $hasOpenIssues)
    {
        $this->name = $name;
        $this->key = $key;
        $this->displayName = $displayName;
        $this->email = $email;
        $this->deleted = $deleted;
        $this->active = $active;
        $this->inGroups = $inGroups;
        $this->hasApplicationRoles = $hasApplicationRoles;
        $this->hasOpenIssues = $hasOpenIssues;
    }
}
