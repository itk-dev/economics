<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $projectId = null;

    #[ORM\Column]
    private ?bool $recorded = null;

    #[ORM\Column(nullable: true)]
    private ?int $customerAccountId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $recordedDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $exportedDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedContactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedAccountKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedSalesChannel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paidByAccount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultPayToAccount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultMaterialNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periodFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periodTo = null;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceEntry::class)]
    private Collection $invoiceEntries;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    private ?Project $project = null;

    public function __construct()
    {
        $this->invoiceEntries = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(int $projectId): self
    {
        $this->projectId = $projectId;

        return $this;
    }

    public function isRecorded(): ?bool
    {
        return $this->recorded;
    }

    public function setRecorded(bool $recorded): self
    {
        $this->recorded = $recorded;

        return $this;
    }

    public function getCustomerAccountId(): ?int
    {
        return $this->customerAccountId;
    }

    public function setCustomerAccountId(?int $customerAccountId): self
    {
        $this->customerAccountId = $customerAccountId;

        return $this;
    }

    public function getRecordedDate(): ?\DateTimeInterface
    {
        return $this->recordedDate;
    }

    public function setRecordedDate(?\DateTimeInterface $recordedDate): self
    {
        $this->recordedDate = $recordedDate;

        return $this;
    }

    public function getExportedDate(): ?\DateTimeInterface
    {
        return $this->exportedDate;
    }

    public function setExportedDate(?\DateTimeInterface $exportedDate): self
    {
        $this->exportedDate = $exportedDate;

        return $this;
    }

    public function getLockedContactName(): ?string
    {
        return $this->lockedContactName;
    }

    public function setLockedContactName(?string $lockedContactName): self
    {
        $this->lockedContactName = $lockedContactName;

        return $this;
    }

    public function getLockedType(): ?string
    {
        return $this->lockedType;
    }

    public function setLockedType(?string $lockedType): self
    {
        $this->lockedType = $lockedType;

        return $this;
    }

    public function getLockedAccountKey(): ?string
    {
        return $this->lockedAccountKey;
    }

    public function setLockedAccountKey(?string $lockedAccountKey): self
    {
        $this->lockedAccountKey = $lockedAccountKey;

        return $this;
    }

    public function getLockedSalesChannel(): ?string
    {
        return $this->lockedSalesChannel;
    }

    public function setLockedSalesChannel(?string $lockedSalesChannel): self
    {
        $this->lockedSalesChannel = $lockedSalesChannel;

        return $this;
    }

    public function getPaidByAccount(): ?string
    {
        return $this->paidByAccount;
    }

    public function setPaidByAccount(?string $paidByAccount): self
    {
        $this->paidByAccount = $paidByAccount;

        return $this;
    }

    public function getDefaultPayToAccount(): ?string
    {
        return $this->defaultPayToAccount;
    }

    public function setDefaultPayToAccount(?string $defaultPayToAccount): self
    {
        $this->defaultPayToAccount = $defaultPayToAccount;

        return $this;
    }

    public function getDefaultMaterialNumber(): ?string
    {
        return $this->defaultMaterialNumber;
    }

    public function setDefaultMaterialNumber(?string $defaultMaterialNumber): self
    {
        $this->defaultMaterialNumber = $defaultMaterialNumber;

        return $this;
    }

    public function getPeriodFrom(): ?\DateTimeInterface
    {
        return $this->periodFrom;
    }

    public function setPeriodFrom(?\DateTimeInterface $periodFrom): self
    {
        $this->periodFrom = $periodFrom;

        return $this;
    }

    public function getPeriodTo(): ?\DateTimeInterface
    {
        return $this->periodTo;
    }

    public function setPeriodTo(?\DateTimeInterface $periodTo): self
    {
        $this->periodTo = $periodTo;

        return $this;
    }

    /**
     * @return Collection<int, InvoiceEntry>
     */
    public function getInvoiceEntries(): Collection
    {
        return $this->invoiceEntries;
    }

    public function addInvoiceEntry(InvoiceEntry $invoiceEntry): self
    {
        if (!$this->invoiceEntries->contains($invoiceEntry)) {
            $this->invoiceEntries->add($invoiceEntry);
            $invoiceEntry->setInvoice($this);
        }

        return $this;
    }

    public function removeInvoiceEntry(InvoiceEntry $invoiceEntry): self
    {
        if ($this->invoiceEntries->removeElement($invoiceEntry)) {
            // set the owning side to null (unless already changed)
            if ($invoiceEntry->getInvoice() === $this) {
                $invoiceEntry->setInvoice(null);
            }
        }

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
