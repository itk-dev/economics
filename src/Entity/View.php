<?php

namespace App\Entity;

use App\Interface\ProtectedInterface;
use App\Repository\ViewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewRepository::class)]
class View extends AbstractBaseEntity implements ProtectedInterface
{
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToMany(targetEntity: DataProvider::class, inversedBy: 'views', fetch: 'EAGER')]
    private Collection $dataProviders;

    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'views', fetch: 'EAGER')]
    private Collection $projects;

    #[ORM\Column(nullable: true)]
    private ?bool $protected = null;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'views')]
    private Collection $users;

    public function __construct()
    {
        $this->dataProviders = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDataProviders(): Collection
    {
        return $this->dataProviders;
    }

    public function addDataProvider(DataProvider $dataProvider): static
    {
        if (!empty($this->dataProviders) && !$this->dataProviders->contains($dataProvider)) {
            $this->dataProviders->add($dataProvider);
        }

        return $this;
    }

    public function removeDataProvider(DataProvider $dataProvider): static
    {
        if (!empty($this->dataProviders)) {
            $this->dataProviders->removeElement($dataProvider);
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

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        $this->projects->removeElement($project);

        return $this;
    }

    /**
     * Get all reference fields of the entity.
     *
     * @return array
     */
    public function getReferenceFields(): array
    {
        return [
            $this->getDataProviders(),
            $this->getProjects(),
        ];
    }

    /**
     * Remove reference regardless of type.
     *
     * @param $reference
     *
     * @return $this
     */
    public function removeReference($reference): static
    {
        if ($reference instanceof Project) {
            $this->removeProject($reference);
        }

        if ($reference instanceof DataProvider) {
            $this->removeDataProvider($reference);
        }

        return $this;
    }

    /**
     * Add reference regardless of type.
     *
     * @param $entity
     *
     * @return $this
     */
    public function AddReference($entity): static
    {
        if ($entity instanceof Project) {
            $this->addProject($entity);
        }

        if ($entity instanceof DataProvider) {
            $this->AddDataProvider($entity);
        }

        return $this;
    }

    public function isProtected(): ?bool
    {
        return $this->protected;
    }

    public function setProtected(?bool $protected): static
    {
        $this->protected = $protected;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addView($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            $user->removeView($this);
        }

        return $this;
    }
}
