<?php

namespace App\Entity;

use App\Repository\ContactRoleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A role a service agreement contact can hold, e.g. "Fakturering".
 *
 * The vocabulary starts empty and grows as users type new roles on a contact,
 * so there is no enum to keep in sync.
 */
#[ORM\Entity(repositoryClass: ContactRoleRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_contact_role_name', columns: ['name'])]
class ContactRole extends AbstractBaseEntity implements \Stringable
{
    #[ORM\Column(length: 255)]
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        // Trimmed on the way in so " Fakturering" cannot slip past the unique
        // index as a second role.
        $this->name = trim($name);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
