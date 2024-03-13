<?php

namespace App\Entity;

use App\Repository\IssueProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueProductRepository::class)]
class IssueProduct extends AbstractBaseEntity
{
    #[ORM\ManyToOne(inversedBy: 'issueProducts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Issue $issue = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private int $quantity = 1;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTotal(): float
    {
        return (float) $this->getProduct()?->getPriceAsFloat() * $this->getQuantity();
    }
}
