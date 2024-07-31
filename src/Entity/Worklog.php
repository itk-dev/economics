<?php

namespace App\Entity;

use App\Entity\Trait\DataProviderTrait;
use App\Enum\BillableKindsEnum;
use App\Repository\WorklogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: WorklogRepository::class)]
#[ORM\UniqueConstraint(name: 'data_provider_project_tracker', columns: ['data_provider_id', 'worklog_id'])]
#[UniqueEntity(fields: ['dataProvider', 'worklogId'])]
class Worklog extends AbstractBaseEntity
{
    use DataProviderTrait;

    #[ORM\Column]
    private ?int $worklogId = null;

    #[ORM\ManyToOne(inversedBy: 'worklogs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InvoiceEntry $invoiceEntry = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isBilled = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $worker = null;

    #[ORM\Column]
    private ?int $timeSpentSeconds = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $started = null;

    #[ORM\ManyToOne(inversedBy: 'worklogs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Project $project = null;

    #[ORM\Column(nullable: true)]
    private ?int $billedSeconds = null;

    #[ORM\ManyToOne(inversedBy: 'worklogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Issue $issue = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerIssueId = null;

    #[ORM\Column(type: 'string', nullable: true, enumType: BillableKindsEnum::class)]
    private ?BillableKindsEnum $kind;

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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

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

    public function getIssue(): ?Issue
    {
        return $this->issue;
    }

    public function setIssue(?Issue $issue): self
    {
        $this->issue = $issue;

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

    public function getKind(): ?BillableKindsEnum
    {
        return $this->kind;
    }

    public function setKind(?BillableKindsEnum $kind): self
    {
        $this->kind = $kind;

        return $this;
    }
}
