<?php

namespace App\Entity;

use App\Repository\ServiceAgreementContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceAgreementContactRepository::class)]
class ServiceAgreementContact extends AbstractBaseEntity
{
    /**
     * Nullable to match the property, which has to accept null: a contact is
     * built before the collection attaches it, and removeContact() detaches it
     * again. Orphan removal deletes a detached contact, so no row is left
     * behind without an agreement.
     */
    #[ORM\ManyToOne(inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServiceAgreement $serviceAgreement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * Roles cascade a persist because a role typed into the widget does not
     * exist yet when the contact is saved.
     *
     * @var Collection<int, ContactRole>
     */
    #[ORM\ManyToMany(targetEntity: ContactRole::class, cascade: ['persist'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getServiceAgreement(): ?ServiceAgreement
    {
        return $this->serviceAgreement;
    }

    public function setServiceAgreement(?ServiceAgreement $serviceAgreement): static
    {
        $this->serviceAgreement = $serviceAgreement;

        return $this;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection<int, ContactRole>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(ContactRole $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }

        return $this;
    }

    public function removeRole(ContactRole $role): static
    {
        $this->roles->removeElement($role);

        return $this;
    }
}
