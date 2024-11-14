<?php

namespace App\Entity;

use App\Entity\Trait\DataProviderTrait;
use App\Repository\VersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: VersionRepository::class)]
#[ORM\UniqueConstraint(name: 'data_provider_project_tracker', columns: ['data_provider_id', 'project_tracker_id'])]
#[UniqueEntity(fields: ['dataProvider', 'projectTrackerId'])]
class Version extends AbstractBaseEntity
{
    use DataProviderTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerId = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToMany(targetEntity: Issue::class, mappedBy: 'versions')]
    private Collection $issues;

    #[ORM\Column(nullable: true)]
    private ?bool $isBillable = false;



    public function __construct()
    {
        $this->issues = new ArrayCollection();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProjectTrackerId(): ?string
    {
        return $this->projectTrackerId;
    }

    public function setProjectTrackerId(string $projectTrackerId): self
    {
        $this->projectTrackerId = $projectTrackerId;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getName() ?? (string) $this->getId();
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function addIssue(Issue $issue): self
    {
        if (!$this->issues->contains($issue)) {
            $this->issues->add($issue);
            $issue->addVersion($this);
        }

        return $this;
    }

    public function removeIssue(Issue $issue): self
    {
        if ($this->issues->removeElement($issue)) {
            $issue->removeVersion($this);
        }

        return $this;
    }

    public function isBillable(): ?bool
    {
        return $this->isBillable;
    }

    public function setIsBillable(?bool $isBillable): self
    {
        $this->isBillable = $isBillable;

        return $this;
    }
}
