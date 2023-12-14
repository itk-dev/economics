<?php

namespace App\Entity;

use App\Repository\ProjectVersionBudgetRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ProjectVersionBudgetRepository::class)]
class ProjectVersionBudget extends AbstractBaseEntity
{
    #[ORM\Column(length: 255)]
    private ?string $projectId = null;

    #[ORM\Column(length: 255)]
    private ?string $versionId = null;

    #[ORM\Column]
    private ?float $budget = null;

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function setProjectId(string $projectId): self
    {
        $this->projectId = $projectId;

        return $this;
    }

    public function getVersionId(): ?string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): self
    {
        $this->versionId = $versionId;

        return $this;
    }

    public function getBudget(): ?float
    {
        return $this->budget;
    }

    public function setBudget(float $budget): self
    {
        $this->budget = $budget;

        return $this;
    }
}
