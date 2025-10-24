<?php

namespace App\Entity;

use App\Enum\HostingProviderEnum;
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

    #[ORM\Column]
    private ?int $projectId = null;

    #[ORM\Column]
    private ?int $clientId = null;

    #[ORM\Column(nullable: true)]
    private ?int $cybersecurityAgreementId = null;

    #[ORM\Column(enumType: HostingProviderEnum::class)]
    private ?HostingProviderEnum $hostingProvider = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentUrl = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column]
    private ?int $projectLeadId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(int $projectId): static
    {
        $this->projectId = $projectId;

        return $this;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(int $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getCybersecurityAgreementId(): ?int
    {
        return $this->cybersecurityAgreementId;
    }

    public function setCybersecurityAgreementId(?int $cybersecurityAgreementId): static
    {
        $this->cybersecurityAgreementId = $cybersecurityAgreementId;

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

    public function getProjectLeadId(): ?int
    {
        return $this->projectLeadId;
    }

    public function setProjectLeadId(int $projectLeadId): static
    {
        $this->projectLeadId = $projectLeadId;

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
}
