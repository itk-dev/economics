<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product extends AbstractBaseEntity
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThan(1_000_000)]
    private ?string $price = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: IssueProduct::class, orphanRemoval: true)]
    private Collection $issues;

    public function __construct()
    {
        $this->issues = new ArrayCollection();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getPriceAsFloat(): float
    {
        return (float) $this->getPrice();
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, IssueProduct>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function addIssue(IssueProduct $issue): static
    {
        if (!$this->issues->contains($issue)) {
            $this->issues->add($issue);
            $issue->setProduct($this);
        }

        return $this;
    }

    public function removeIssue(IssueProduct $issue): static
    {
        if ($this->issues->removeElement($issue)) {
            // set the owning side to null (unless already changed)
            if ($issue->getProduct() === $this) {
                $issue->setProduct(null);
            }
        }

        return $this;
    }
}
