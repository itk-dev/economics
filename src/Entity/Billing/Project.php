<?php

namespace App\Entity\Billing;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    use BlameableEntity;
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerProjectUrl;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerKey;

    #[ORM\Column]
    private ?int $projectTrackerId;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
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

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): self
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setProject($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): self
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getProject() === $this) {
                $invoice->setProject(null);
            }
        }

        return $this;
    }

    public function getProjectTrackerProjectUrl(): ?string
    {
        return $this->projectTrackerProjectUrl;
    }

    public function setProjectTrackerProjectUrl(?string $projectTrackerProjectUrl): void
    {
        $this->projectTrackerProjectUrl = $projectTrackerProjectUrl;
    }

    public function getProjectTrackerKey(): ?string
    {
        return $this->projectTrackerKey;
    }

    public function setProjectTrackerKey(?string $projectTrackerKey): void
    {
        $this->projectTrackerKey = $projectTrackerKey;
    }

    public function getProjectTrackerId(): ?int
    {
        return $this->projectTrackerId;
    }

    public function setProjectTrackerId(?int $projectTrackerId): void
    {
        $this->projectTrackerId = $projectTrackerId;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->id;
    }
}
