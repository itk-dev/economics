<?php

namespace App\Entity;

use App\Enum\MaterialNumberEnum;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice extends AbstractBaseEntity
{
    // Opus allows at most 500 characters (cf. BillingService::exportInvoicesToSpreadsheet).
    public const DESCRIPTION_MAX_LENGTH = 500;

    #[ORM\Column(length: 255)]
    private ?string $name = null;


    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $recorded = null;

    #[ORM\Column(nullable: true)]
    private ?int $customerAccountId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $recordedDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $exportedDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedCustomerKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedContactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedEan = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lockedSalesChannel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paidByAccount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultReceiverAccount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?MaterialNumberEnum $defaultMaterialNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periodFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periodTo = null;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceEntry::class, cascade: ['remove'])]
    #[ORM\OrderBy(['index' => Criteria::ASC])]
    private Collection $invoiceEntries;

    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'invoices')]
    private ?Project $project = null;

    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'invoices')]
    private ?Client $client = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalPrice = null;

    #[ORM\ManyToOne(inversedBy: 'invoices')]
    private ?ProjectBilling $projectBilling = null;

    #[ORM\Column]
    private bool $noCost = false;

    public function __construct()
    {
        $this->invoiceEntries = new ArrayCollection();
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

    public function getLockedEan(): ?string
    {
        return $this->lockedEan;
    }

    public function setLockedEan(?string $lockedEan): self
    {
        $this->lockedEan = $lockedEan;

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

    public function setInvoiceEntryIndexes()
    {
        $index = 0;
        foreach ($this->getInvoiceEntries() as $entry) {
            $entry->setIndex($index);
            ++$index;
        }
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getLockedCustomerKey(): ?string
    {
        return $this->lockedCustomerKey;
    }

    public function setLockedCustomerKey(?string $lockedCustomerKey): self
    {
        $this->lockedCustomerKey = $lockedCustomerKey;

        return $this;
    }

    public function getDefaultMaterialNumber(): ?MaterialNumberEnum
    {
        return $this->defaultMaterialNumber;
    }

    public function setDefaultMaterialNumber(?MaterialNumberEnum $defaultMaterialNumber): self
    {
        $this->defaultMaterialNumber = $defaultMaterialNumber;

        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): self
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getPaidByAccount(): ?string
    {
        return $this->paidByAccount;
    }

    public function setPaidByAccount(?string $paidByAccount): void
    {
        $this->paidByAccount = $paidByAccount;
    }

    public function getDefaultReceiverAccount(): ?string
    {
        return $this->defaultReceiverAccount;
    }

    public function setDefaultReceiverAccount(?string $defaultReceiverAccount): void
    {
        $this->defaultReceiverAccount = $defaultReceiverAccount;
    }

    public function getProjectBilling(): ?ProjectBilling
    {
        return $this->projectBilling;
    }

    public function setProjectBilling(?ProjectBilling $projectBilling): self
    {
        $this->projectBilling = $projectBilling;

        return $this;
    }

    public function isNoCost(): bool
    {
        return $this->noCost;
    }

    public function setNoCost(bool $noCost): self
    {
        $this->noCost = $noCost;

        return $this;
    }
}
