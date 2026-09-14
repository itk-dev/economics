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
        $this->name = self::normalizeName($name);

        return $this;
    }

    /**
     * The name as it is stored.
     *
     * Trimmed so " Fakturering" cannot slip past the unique index as a second
     * role, and the first letter upper-cased so a hurried "fakturering" does
     * not sit in the list beside a stored "Fakturering".
     *
     * Only the first letter: ucwords() or MB_CASE_TITLE would rewrite
     * "IT-kontakt" to "It-Kontakt", and the rest of the name is the user's to
     * case. mb_* throughout, because a Danish role name can open on æ, ø or å
     * and ucfirst() would corrupt the first byte of one.
     *
     * Public because ServiceAgreementContactType has to build the same
     * spelling when it rebuilds the choice list around a submitted name.
     */
    public static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            return '';
        }

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
