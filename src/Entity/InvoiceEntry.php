<?php

namespace App\Entity;

use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use App\Repository\InvoiceEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class InvoiceEntry extends AbstractBaseEntity
{
    #[ORM\ManyToOne(inversedBy: 'invoiceEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\Column(type: Types::INTEGER, name: 'entry_index')]
    private ?int $index = null;

    // TODO: Remove since it is unused.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    // TODO: Rename to legacy field.
    // TODO: Add migration from account to receiverAccount.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $account = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $product = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    private ?float $amount = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalPrice = null;

    #[ORM\Column(length: 255)]
    private InvoiceEntryTypeEnum $entryType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?MaterialNumberEnum $materialNumber = null;

    #[ORM\OneToMany(mappedBy: 'invoiceEntry', targetEntity: Worklog::class)]
    private Collection $worklogs;

    #[ORM\OneToMany(mappedBy: 'invoiceEntry', targetEntity: IssueProduct::class)]
    private Collection $issueProducts;

    public function __construct()
    {
        $this->worklogs = new ArrayCollection();
        $this->issueProducts = new ArrayCollection();
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function setIndex(int $index): self
    {
        $this->index = $index;

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

    public function getAccount(): ?string
    {
        return $this->account;
    }

    public function setAccount(?string $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function setProduct(?string $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getMaterialNumber(): ?MaterialNumberEnum
    {
        return $this->materialNumber;
    }

    public function setMaterialNumber(?MaterialNumberEnum $materialNumber): self
    {
        $this->materialNumber = $materialNumber;

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
            $worklog->setInvoiceEntry($this);
        }

        return $this;
    }

    public function removeWorklog(Worklog $worklog): self
    {
        if ($this->worklogs->removeElement($worklog)) {
            // set the owning side to null (unless already changed)
            if ($worklog->getInvoiceEntry() === $this) {
                $worklog->setInvoiceEntry(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueProduct>
     */
    public function getIssueProducts(): Collection
    {
        return $this->issueProducts;
    }

    public function addIssueProduct(IssueProduct $issueProduct): self
    {
        if (!$this->issueProducts->contains($issueProduct)) {
            $this->issueProducts->add($issueProduct);
            $issueProduct->setInvoiceEntry($this);
        }

        return $this;
    }

    public function removeIssueProduct(IssueProduct $issueProduct): self
    {
        if ($this->issueProducts->removeElement($issueProduct)) {
            // set the owning side to null (unless already changed)
            if ($issueProduct->getInvoiceEntry() === $this) {
                $issueProduct->setInvoiceEntry(null);
            }
        }

        return $this;
    }

    public function getEntryType(): InvoiceEntryTypeEnum
    {
        return $this->entryType;
    }

    public function setEntryType(InvoiceEntryTypeEnum $entryType): self
    {
        $this->entryType = $entryType;

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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setInvoiceIndex()
    {
        $this->getInvoice()?->setInvoiceEntryIndexes();
    }
}
