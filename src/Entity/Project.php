<?php

namespace App\Entity;

use App\Entity\Trait\DataProviderTrait;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project extends AbstractBaseEntity
{
    use DataProviderTrait;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerProjectUrl;

    #[ORM\Column(length: 255)]
    private ?string $projectTrackerKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $projectTrackerId;

    #[ORM\ManyToMany(targetEntity: Client::class, inversedBy: 'projects')]
    private Collection $clients;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Version::class)]
    private Collection $versions;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Worklog::class)]
    private Collection $worklogs;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectBilling::class)]
    private Collection $projectBillings;

    #[ORM\Column(nullable: true)]
    private ?bool $include = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Issue::class)]
    private Collection $issues;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $projectLeadName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $projectLeadMail = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Product::class, orphanRemoval: true)]
    private Collection $products;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->versions = new ArrayCollection();
        $this->worklogs = new ArrayCollection();
        $this->projectBillings = new ArrayCollection();
        $this->issues = new ArrayCollection();
        $this->products = new ArrayCollection();
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

    public function getProjectTrackerId(): ?string
    {
        return $this->projectTrackerId;
    }

    public function setProjectTrackerId(?string $projectTrackerId): void
    {
        $this->projectTrackerId = $projectTrackerId;
    }

    public function __toString(): string
    {
        return (string) ($this->name ?? $this->id);
    }

    /**
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): self
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
        }

        return $this;
    }

    public function removeClient(Client $client): self
    {
        $this->clients->removeElement($client);

        return $this;
    }

    /**
     * @return Collection<int, Version>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(Version $version): self
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setProject($this);
        }

        return $this;
    }

    public function removeVersion(Version $version): self
    {
        if ($this->versions->removeElement($version)) {
            $version->setProject(null);
        }

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
            $worklog->setProject($this);
        }

        return $this;
    }

    public function removeWorklog(Worklog $worklog): self
    {
        if ($this->worklogs->removeElement($worklog)) {
            // set the owning side to null (unless already changed)
            if ($worklog->getProject() === $this) {
                $worklog->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectBilling>
     */
    public function getProjectBillings(): Collection
    {
        return $this->projectBillings;
    }

    public function addProjectBilling(ProjectBilling $projectBilling): self
    {
        if (!$this->projectBillings->contains($projectBilling)) {
            $this->projectBillings->add($projectBilling);
            $projectBilling->setProject($this);
        }

        return $this;
    }

    public function removeProjectBilling(ProjectBilling $projectBilling): self
    {
        if ($this->projectBillings->removeElement($projectBilling)) {
            // set the owning side to null (unless already changed)
            if ($projectBilling->getProject() === $this) {
                $projectBilling->setProject(null);
            }
        }

        return $this;
    }

    public function isInclude(): ?bool
    {
        return $this->include;
    }

    public function setInclude(?bool $include): self
    {
        $this->include = $include;

        return $this;
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function addIssue(Issue $issue): self
    {
        if (!$this->issues->contains($issue)) {
            $this->issues->add($issue);
            $issue->setProject($this);
        }

        return $this;
    }

    public function removeIssue(Issue $issue): self
    {
        if ($this->issues->removeElement($issue)) {
            // set the owning side to null (unless already changed)
            if ($issue->getProject() === $this) {
                $issue->setProject(null);
            }
        }

        return $this;
    }

    public function getProjectLeadName(): ?string
    {
        return $this->projectLeadName;
    }

    public function setProjectLeadName(?string $projectLeadName): static
    {
        $this->projectLeadName = $projectLeadName;

        return $this;
    }

    public function getProjectLeadMail(): ?string
    {
        return $this->projectLeadMail;
    }

    public function setProjectLeadMail(?string $projectLeadMail): static
    {
        $this->projectLeadMail = $projectLeadMail;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setProject($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getProject() === $this) {
                $product->setProject(null);
            }
        }

        return $this;
    }
}
