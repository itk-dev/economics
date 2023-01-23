<?php

namespace App\Entity;

use App\Enum\ClientTypeEnum;
use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(nullable: true)]
    private ?float $standardPrice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?ClientTypeEnum $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $account = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $psp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ean = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'clients')]
    private Collection $projects;

    #[ORM\Column]
    private int $projectTrackerId;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
        $this->projects = new ArrayCollection();
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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getStandardPrice(): ?float
    {
        return $this->standardPrice;
    }

    public function setStandardPrice(float $standardPrice): self
    {
        $this->standardPrice = $standardPrice;

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

    public function getPsp(): ?string
    {
        return $this->psp;
    }

    public function setPsp(?string $psp): self
    {
        $this->psp = $psp;

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
            $invoice->setClient($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): self
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getClient() === $this) {
                $invoice->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addClient($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            $project->removeClient($this);
        }

        return $this;
    }

    public function getProjectTrackerId(): int
    {
        return $this->projectTrackerId;
    }

    public function setProjectTrackerId(int $projectTrackerId): void
    {
        $this->projectTrackerId = $projectTrackerId;
    }

    public function getType(): ?ClientTypeEnum
    {
        return $this->type;
    }

    public function setType(?ClientTypeEnum $type): void
    {
        $this->type = $type;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
