<?php

namespace App\Entity;

use App\Enum\HostingProviderEnum;
use App\Enum\ServerSizeEnum;
use App\Enum\SystemOwnerNoticeEnum;
use App\Repository\ServiceAgreementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ServiceAgreementRepository::class)]
class ServiceAgreement extends AbstractBaseEntity
{
    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'serviceAgreements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: CybersecurityAgreement::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?CybersecurityAgreement $cybersecurityAgreement = null;

    #[ORM\Column(enumType: HostingProviderEnum::class)]
    private ?HostingProviderEnum $hostingProvider = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentUrl = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\ManyToOne(targetEntity: Worker::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Worker $projectLead = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(type: Types::JSON)]
    private array $systemOwnerNotices = [];

    #[ORM\Column]
    private bool $isEol = false;

    /**
     * @deprecated superseded by $contacts. Retained so the migration to the
     *             contact list loses nothing; not editable through any form.
     *             Read it only to recover pre-migration data.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientContactName = null;

    /**
     * @deprecated superseded by $contacts — see $clientContactName
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientContactEmail = null;

    /**
     * Contacts cascade a persist because the controller persists only the
     * agreement itself, and orphan removal is what the "remove row" button in
     * the form relies on.
     *
     * @var Collection<int, ServiceAgreementContact>
     */
    #[ORM\OneToMany(mappedBy: 'serviceAgreement', targetEntity: ServiceAgreementContact::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $contacts;

    #[ORM\Column]
    private bool $dedicatedServer = false;

    #[ORM\Column(enumType: ServerSizeEnum::class, nullable: true)]
    private ?ServerSizeEnum $serverSize = null;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getCybersecurityAgreement(): ?CybersecurityAgreement
    {
        return $this->cybersecurityAgreement;
    }

    public function setCybersecurityAgreement(?CybersecurityAgreement $cybersecurityAgreement): static
    {
        $this->cybersecurityAgreement = $cybersecurityAgreement;

        return $this;
    }

    public function getHostingProvider(): ?HostingProviderEnum
    {
        return $this->hostingProvider;
    }

    public function setHostingProvider(HostingProviderEnum $hostingProvider): static
    {
        $this->hostingProvider = $hostingProvider;

        return $this;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->documentUrl;
    }

    public function setDocumentUrl(?string $documentUrl): static
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getProjectLead(): ?Worker
    {
        return $this->projectLead;
    }

    public function setProjectLead(?Worker $projectLead): static
    {
        $this->projectLead = $projectLead;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeInterface
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeInterface $validTo): static
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return SystemOwnerNoticeEnum[]
     */
    public function getSystemOwnerNotices(): array
    {
        return array_map(
            fn (string $value) => SystemOwnerNoticeEnum::from($value),
            $this->systemOwnerNotices
        );
    }

    /**
     * @param SystemOwnerNoticeEnum[] $notices
     */
    public function setSystemOwnerNotices(array $notices): static
    {
        $this->systemOwnerNotices = array_map(
            fn (SystemOwnerNoticeEnum $notice) => $notice->value,
            $notices
        );

        return $this;
    }

    public function isEol(): bool
    {
        return $this->isEol;
    }

    public function setIsEol(bool $isEol): static
    {
        $this->isEol = $isEol;

        return $this;
    }

    /**
     * @deprecated use getContacts()
     */
    public function getClientContactName(): ?string
    {
        return $this->clientContactName;
    }

    /**
     * @deprecated use getContacts()
     */
    public function setClientContactName(?string $clientContactName): static
    {
        $this->clientContactName = $clientContactName;

        return $this;
    }

    /**
     * @deprecated use getContacts()
     */
    public function getClientContactEmail(): ?string
    {
        return $this->clientContactEmail;
    }

    /**
     * @deprecated use getContacts()
     */
    public function setClientContactEmail(?string $clientContactEmail): static
    {
        $this->clientContactEmail = $clientContactEmail;

        return $this;
    }

    /**
     * @return Collection<int, ServiceAgreementContact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(ServiceAgreementContact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setServiceAgreement($this);
        }

        return $this;
    }

    public function removeContact(ServiceAgreementContact $contact): static
    {
        if ($this->contacts->removeElement($contact) && $contact->getServiceAgreement() === $this) {
            $contact->setServiceAgreement(null);
        }

        return $this;
    }

    public function isDedicatedServer(): bool
    {
        return $this->dedicatedServer;
    }

    public function setDedicatedServer(bool $dedicatedServer): static
    {
        $this->dedicatedServer = $dedicatedServer;

        return $this;
    }

    public function getServerSize(): ?ServerSizeEnum
    {
        return $this->serverSize;
    }

    public function setServerSize(?ServerSizeEnum $serverSize): static
    {
        $this->serverSize = $serverSize;

        return $this;
    }

    #[Assert\Callback]
    public function validateValidTo(ExecutionContextInterface $context): void
    {
        if ($this->isEol && null === $this->validTo) {
            $context->buildViolation('service_agreement.valid_to_required_when_eol')
                ->atPath('validTo')
                ->addViolation();
        }
    }
}
