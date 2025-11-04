<?php

namespace App\Entity;

use App\Repository\CybersecurityAgreementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CybersecurityAgreementRepository::class)]
class CybersecurityAgreement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceAgreement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceAgreement $serviceAgreement = null;

    #[ORM\Column(nullable: true)]
    private ?float $quarterlyHours = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getQuarterlyHours(): ?float
    {
        return $this->quarterlyHours;
    }

    public function setQuarterlyHours(float $quarterlyHours): static
    {
        $this->quarterlyHours = $quarterlyHours;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
