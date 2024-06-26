<?php

namespace App\Entity;

use App\Repository\IssueProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueProductRepository::class)]
class IssueProduct extends AbstractBaseEntity
{
    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Issue $issue = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private ?float $quantity;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'issueProducts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InvoiceEntry $invoiceEntry = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isBilled = null;

    public function getIssue(): ?Issue
    {
        return $this->issue;
    }

    public function setIssue(?Issue $issue): static
    {
        $this->issue = $issue;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTotal(): float
    {
        return (float) $this->getProduct()?->getPriceAsFloat() * $this->getQuantity();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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
}
