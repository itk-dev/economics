<?php

namespace App\Entity;

use App\Repository\ContactRoleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    /**
     * The length is validated, not just mapped: the tag widget's choice list is
     * widened on submit to whatever arrived, so ChoiceType's own guard is gone
     * and a longer name would otherwise reach the column and fail there.
     */
    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
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
