<?php

namespace App\Entity;

use App\Entity\Trait\DataProviderTrait;
use App\Enum\IssueStatusEnum;
use App\Repository\IssueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
class Issue extends AbstractBaseEntity
{
    use DataProviderTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', nullable: true, enumType: IssueStatusEnum::class)]
    private ?IssueStatusEnum $status = null;

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

    #[ORM\ManyToOne(inversedBy: 'issues')]
    private ?Project $project = null;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: IssueProduct::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => Criteria::ASC])]
    private Collection $products;

    #[ORM\Column(length: 255, nullable: true)]
    public ?float $planHours;

    #[ORM\Column(length: 255, nullable: true)]
    public ?float $hoursRemaining;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(length: 255)]
    private ?string $worker = null;

    #[ORM\Column(length: 255)]
    private ?string $linkToIssue = null;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
        $this->worklogs = new ArrayCollection();
        $this->products = new ArrayCollection();
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

    public function getStatus(): ?IssueStatusEnum
    {
        return $this->status;
    }

    public function setStatus(IssueStatusEnum $status): self
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, IssueProduct>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(IssueProduct $issueProduct): static
    {
        if (!$this->products->contains($issueProduct)) {
            $this->products->add($issueProduct);
            $issueProduct->setIssue($this);
        }

        return $this;
    }

    public function removeProduct(IssueProduct $issueProduct): static
    {
        if ($this->products->removeElement($issueProduct)) {
            // set the owning side to null (unless already changed)
            if ($issueProduct->getIssue() === $this) {
                $issueProduct->setIssue(null);
            }
        }

        return $this;
    }

    public function getPlanHours(): ?float
    {
        return $this->planHours;
    }

    public function setPlanHours(?float $planHours): self
    {
        $this->planHours = $planHours;

        return $this;
    }

    public function getHoursRemaining(): ?float
    {
        return $this->planHours;
    }

    public function setHoursRemaining(?float $hoursRemaining): self
    {
        $this->hoursRemaining = $hoursRemaining;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getWorker(): ?string
    {
        return $this->worker;
    }

    public function setWorker(string $worker): self
    {
        $this->worker = $worker;

        return $this;
    }

    public function getLinkToIssue(): ?string
    {
        return $this->linkToIssue;
    }

    public function setLinkToIssue(?string $linkToIssue): self
    {
        $this->linkToIssue = $linkToIssue;

        return $this;
    }
}
