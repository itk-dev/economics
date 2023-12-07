<?php

namespace App\Entity;

use App\Entity\Trait\DataProviderTrait;
use App\Repository\IssueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
class Issue extends AbstractBaseEntity
{
    use DataProviderTrait;

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
    private ?string $tagKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tagName = null;

    #[ORM\ManyToMany(targetEntity: Milestone::class, inversedBy: 'issues')]
    private Collection $milestones;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: Worklog::class)]
    private Collection $worklogs;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolutionDate = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    private ?Project $project = null;

    public function __construct()
    {
        $this->milestones = new ArrayCollection();
        $this->worklogs = new ArrayCollection();
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

    public function getTagKey(): ?string
    {
        return $this->tagKey;
    }

    public function setTagKey(?string $tagKey): self
    {
        $this->tagKey = $tagKey;

        return $this;
    }

    public function getTagName(): ?string
    {
        return $this->tagName;
    }

    public function setTagName(?string $tagName): self
    {
        $this->tagName = $tagName;

        return $this;
    }

    /**
     * @return Collection<int, Milestone>
     */
    public function getMilestones(): Collection
    {
        return $this->milestones;
    }

    public function addMilestone(Milestone $milestone): self
    {
        if (!$this->milestones->contains($milestone)) {
            $this->milestones->add($milestone);
        }

        return $this;
    }

    public function removeMilestone(Milestone $milestone): self
    {
        $this->milestones->removeElement($milestone);

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
