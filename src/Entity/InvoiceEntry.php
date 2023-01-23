<?php

namespace App\Entity;

use App\Enum\InvoiceEntryTypeEnum;
use App\Repository\InvoiceEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: InvoiceEntryRepository::class)]
class InvoiceEntry
{
    use BlameableEntity;
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invoiceEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $account = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $product = null;

    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    private ?int $amount = null;

    #[ORM\Column(nullable: true)]
    private ?float $totalPrice = null;

    #[ORM\Column(length: 255)]
    private InvoiceEntryTypeEnum $entryType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $materialNumber = null;

    #[ORM\OneToMany(mappedBy: 'invoiceEntry', targetEntity: Worklog::class)]
    private Collection $worklogs;

    public function __construct()
    {
        $this->worklogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getMaterialNumber(): ?string
    {
        return $this->materialNumber;
    }

    public function setMaterialNumber(?string $materialNumber): self
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

    public function getEntryType(): InvoiceEntryTypeEnum
    {
        return $this->entryType;
    }

    public function setEntryType(InvoiceEntryTypeEnum $entryType): void
    {
        $this->entryType = $entryType;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }
}
