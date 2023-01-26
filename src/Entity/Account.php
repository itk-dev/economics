<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $value = null;

    #[ORM\OneToMany(mappedBy: 'defaultReceiverAccount', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\OneToMany(mappedBy: 'receiverAccount', targetEntity: InvoiceEntry::class)]
    private Collection $invoiceEntries;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function __toString(): string
    {
        $name = $this->getName();
        $value = $this->getValue();

        return "$value: $name";
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
            $invoiceEntry->setReceiverAccount($this);
        }

        return $this;
    }

    public function removeInvoiceEntry(InvoiceEntry $invoiceEntry): self
    {
        if ($this->invoiceEntries->removeElement($invoiceEntry)) {
            // set the owning side to null (unless already changed)
            if ($invoiceEntry->getReceiverAccount() === $this) {
                $invoiceEntry->setReceiverAccount(null);
            }
        }

        return $this;
    }
}
