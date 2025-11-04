<?php

namespace App\Entity;

use App\Enum\HostingProviderEnum;
use App\Enum\SystemOwnerNoticeEnum;
use App\Repository\ServiceAgreementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceAgreementRepository::class)]
class ServiceAgreement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
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

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(enumType: SystemOwnerNoticeEnum::class)]
    private ?SystemOwnerNoticeEnum $SystemOwnerNotice = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function setValidTo(\DateTimeInterface $validTo): static
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

    public function getSystemOwnerNotice(): ?SystemOwnerNoticeEnum
    {
        return $this->SystemOwnerNotice;
    }

    public function setSystemOwnerNotice(SystemOwnerNoticeEnum $SystemOwnerNotice): static
    {
        $this->SystemOwnerNotice = $SystemOwnerNotice;

        return $this;
    }
}
