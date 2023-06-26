<?php

namespace App\Entity;

use App\Repository\IssueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
class Issue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountId = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerId = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $epicKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $epicName = null;

    #[ORM\ManyToMany(targetEntity: Version::class, inversedBy: 'issues')]
    private Collection $versions;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: Worklog::class)]
    private Collection $worklogs;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolutionDate = null;

    #[ORM\Column(length: 255)]
    private ?string $source = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    private ?Project $project = null;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->worklogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAccountKey(): ?string
    {
        return $this->accountKey;
    }

    public function setAccountKey(?string $accountKey): self
    {
        $this->accountKey = $accountKey;

        return $this;
    }

    public function getAccountId(): ?string
    {
        return $this->accountId;
    }

    public function setAccountId(?string $accountId): self
    {
        $this->accountId = $accountId;

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

    public function getProjectTrackerKey(): ?string
    {
        return $this->projectTrackerKey;
    }

    public function setProjectTrackerKey(string $projectTrackerKey): self
    {
        $this->projectTrackerKey = $projectTrackerKey;

        return $this;
    }

    public function getEpicKey(): ?string
    {
        return $this->epicKey;
    }

    public function setEpicKey(?string $epicKey): self
    {
        $this->epicKey = $epicKey;

        return $this;
    }

    public function getEpicName(): ?string
    {
        return $this->epicName;
    }

    public function setEpicName(?string $epicName): self
    {
        $this->epicName = $epicName;

        return $this;
    }

    /**
     * @return Collection<int, Version>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(Version $version): self
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
        }

        return $this;
    }

    public function removeVersion(Version $version): self
    {
        $this->versions->removeElement($version);

        return $this;
    }

    /**
     * @return Collection<int, Worklog>
     */
    public function getWorklogs(): Collection
    {
        return $this->worklogs;
    }

    public function addWorklog(Worklog $worklog): self
    {
        if (!$this->worklogs->contains($worklog)) {
            $this->worklogs->add($worklog);
            $worklog->setIssue($this);
        }

        return $this;
    }

    public function removeWorklog(Worklog $worklog): self
    {
        if ($this->worklogs->removeElement($worklog)) {
            // set the owning side to null (unless already changed)
            if ($worklog->getIssue() === $this) {
                $worklog->setIssue(null);
            }
        }

        return $this;
    }

    public function getResolutionDate(): ?\DateTimeInterface
    {
        return $this->resolutionDate;
    }

    public function setResolutionDate(?\DateTimeInterface $resolutionDate): self
    {
        $this->resolutionDate = $resolutionDate;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

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
}