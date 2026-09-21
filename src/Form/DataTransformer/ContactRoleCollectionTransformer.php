<?php

namespace App\Form\DataTransformer;

use App\Entity\ContactRole;
use App\Repository\ContactRoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Maps a contact's roles to the plain names the tag widget submits, creating a
 * role that does not exist yet.
 *
 * @implements DataTransformerInterface<Collection<int, ContactRole>, string[]>
 */
class ContactRoleCollectionTransformer implements DataTransformerInterface, ResetInterface
{
    /**
     * Roles created during this request, keyed by lowercased name.
     *
     * Every contact in a collection submit runs through the same transformer
     * instance, so two contacts introducing the same new role must end up
     * sharing one entity — a second one would trip the unique index.
     *
     * @var array<string, ContactRole>
     */
    private array $created = [];

    public function __construct(
        private readonly ContactRoleRepository $contactRoleRepository,
    ) {
    }

    /**
     * The cache above is request scoped, but the service holding it is shared.
     *
     * Anywhere the container outlives a request — a worker, or a functional test
     * that called disableReboot() — a role cached here would be handed to the
     * next request's cascade after the entity manager had been cleared, which
     * fails as a detached entity rather than as anything legible.
     */
    public function reset(): void
    {
        $this->created = [];
    }

    /**
     * @param Collection<int, ContactRole>|null $value
     *
     * @return string[]
     */
    public function transform(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        return array_values(array_map(
            fn (ContactRole $role) => $role->getName(),
            $value->toArray()
        ));
    }

    /**
     * @param string[]|null $value
     *
     * @return Collection<int, ContactRole>
     */
    public function reverseTransform(mixed $value): Collection
    {
        /** @var Collection<int, ContactRole> $roles */
        $roles = new ArrayCollection();

        foreach ($value ?? [] as $name) {
            $name = trim((string) $name);

            if ('' === $name) {
                continue;
            }

            $role = $this->resolve($name);

            if (!$roles->contains($role)) {
                $roles->add($role);
            }
        }

        return $roles;
    }

    private function resolve(string $name): ContactRole
    {
        $key = mb_strtolower($name);

        if (isset($this->created[$key])) {
            return $this->created[$key];
        }

        $existing = $this->contactRoleRepository->findOneByName($name);

        if (null !== $existing) {
            return $existing;
        }

        // Left unpersisted; the cascade on ServiceAgreementContact::$roles saves it.
        $role = new ContactRole();
        $role->setName($name);

        return $this->created[$key] = $role;
    }
}
