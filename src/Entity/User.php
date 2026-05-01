<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferences = null;

    public function __construct()
    {
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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
     * @return array<string, mixed>
     */
    public function getPreferences(): array
    {
        return $this->preferences ?? [];
    }

    /**
     * @param array<string, mixed> $preferences
     */
    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getHiddenWorkers(): array
    {
        $hidden = $this->getPreferences()['hiddenWorkers'] ?? [];

        return is_array($hidden) ? array_values(array_filter($hidden, 'is_string')) : [];
    }

    /**
     * @param string[] $hiddenWorkers
     */
    public function setHiddenWorkers(array $hiddenWorkers): self
    {
        $preferences = $this->getPreferences();
        $preferences['hiddenWorkers'] = array_values(array_unique(array_filter($hiddenWorkers, 'is_string')));
        $this->preferences = $preferences;

        return $this;
    }
}
