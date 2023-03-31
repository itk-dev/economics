<?php

namespace App\Entity;

use App\Repository\WorklogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: WorklogRepository::class)]
class Worklog
{
    use BlameableEntity;
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $worklogId = null;

    #[ORM\ManyToOne(inversedBy: 'worklogs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InvoiceEntry $invoiceEntry = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isBilled = null;

    #[ORM\ManyToMany(targetEntity: Version::class, mappedBy: 'worklogs')]
    private Collection $versions;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $worker = null;

    #[ORM\Column]
    private ?int $timeSpentSeconds = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $started = null;

    #[ORM\Column(length: 255)]
    private ?string $issueName = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerIssueId = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerIssueKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $epicName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $epicKey = null;

    #[ORM\ManyToOne(inversedBy: 'worklogs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    private ?string $source = null;

    #[ORM\Column(nullable: true)]
    private ?int $billedSeconds = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $issueStatus = null;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorklogId(): ?int
    {
        return $this->worklogId;
    }

    public function setWorklogId(int $worklogId): self
    {
        $this->worklogId = $worklogId;

        return $this;
    }

    public function getInvoiceEntry(): ?InvoiceEntry
    {
        return $this->invoiceEntry;
    }

    public function setInvoiceEntry(?InvoiceEntry $invoiceEntry): self
    {
        $this->invoiceEntry = $invoiceEntry;

        return $this;
    }

    public function isBilled(): ?bool
    {
        return $this->isBilled;
    }

    public function setIsBilled(bool $isBilled): self
    {
        $this->isBilled = $isBilled;

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
            $version->addWorklog($this);
        }

        return $this;
    }

    public function removeVersion(Version $version): self
    {
        if ($this->versions->removeElement($version)) {
            $version->removeWorklog($this);
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getTimeSpentSeconds(): ?int
    {
        return $this->timeSpentSeconds;
    }

    public function setTimeSpentSeconds(int $timeSpentSeconds): self
    {
        $this->timeSpentSeconds = $timeSpentSeconds;

        return $this;
    }

    public function getStarted(): ?\DateTimeInterface
    {
        return $this->started;
    }

    public function setStarted(\DateTimeInterface $started): self
    {
        $this->started = $started;

        return $this;
    }

    public function getIssueName(): ?string
    {
        return $this->issueName;
    }

    public function setIssueName(string $issueName): self
    {
        $this->issueName = $issueName;

        return $this;
    }

    public function getProjectTrackerIssueId(): ?string
    {
        return $this->projectTrackerIssueId;
    }

    public function setProjectTrackerIssueId(string $projectTrackerIssueId): self
    {
        $this->projectTrackerIssueId = $projectTrackerIssueId;

        return $this;
    }

    public function getProjectTrackerIssueKey(): ?string
    {
        return $this->projectTrackerIssueKey;
    }

    public function setProjectTrackerIssueKey(string $projectTrackerIssueKey): self
    {
        $this->projectTrackerIssueKey = $projectTrackerIssueKey;

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

    public function getEpicKey(): ?string
    {
        return $this->epicKey;
    }

    public function setEpicKey(?string $epicKey): self
    {
        $this->epicKey = $epicKey;

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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getBilledSeconds(): ?int
    {
        return $this->billedSeconds;
    }

    public function setBilledSeconds(?int $billedSeconds): self
    {
        $this->billedSeconds = $billedSeconds;

        return $this;
    }

    public function getIssueStatus(): ?string
    {
        return $this->issueStatus;
    }

    public function setIssueStatus(?string $issueStatus): self
    {
        $this->issueStatus = $issueStatus;

        return $this;
    }
}
