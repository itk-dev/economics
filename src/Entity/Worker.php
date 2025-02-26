<?php

namespace App\Entity;

use App\Repository\WorkerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: WorkerRepository::class)]
class Worker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?float $workload = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(nullable: false, options: ['default' => true])]
    private bool $includeInReports = true;

    /**
     * @var Collection<int, WorkerGroup>
     */
    #[ORM\ManyToMany(targetEntity: WorkerGroup::class, inversedBy: 'workers')]
    private Collection $workerGroups;

    public function __construct()
    {
        $this->workerGroups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getWorkload(): ?float
    {
        return $this->workload;
    }

    public function setWorkload(?float $workload): self
    {
        $this->workload = $workload;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getIncludeInReports(): bool
    {
        return $this->includeInReports;
    }

    public function setIncludeInReports(bool $includeInReports): self
    {
        $this->includeInReports = $includeInReports;

        return $this;
    }

    /**
     * @return Collection<int, WorkerGroup>
     */
    public function getWorkerGroups(): Collection
    {
        return $this->workerGroups;
    }

    public function addWorkerGroup(WorkerGroup $workerGroup): static
    {
        if (!$this->workerGroups->contains($workerGroup)) {
            $this->workerGroups->add($workerGroup);
        }

        return $this;
    }

    public function removeWorkerGroup(WorkerGroup $workerGroup): static
    {
        $this->workerGroups->removeElement($workerGroup);

        return $this;
    }
}
