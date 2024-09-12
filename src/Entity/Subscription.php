<?php

namespace App\Entity;

use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription extends AbstractBaseEntity
{
    #[ORM\Column(length: 180, unique: false)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', nullable: true, enumType: SubscriptionSubjectEnum::class)]
    private ?SubscriptionSubjectEnum $subject = null;

    #[ORM\Column(type: 'string', nullable: true, enumType: SubscriptionFrequencyEnum::class)]
    private ?SubscriptionFrequencyEnum $frequency = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastSent = null;

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

    public function getSubject(): SubscriptionSubjectEnum
    {
        return $this->subject;
    }

    public function setSubject(SubscriptionSubjectEnum $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getFrequency(): SubscriptionFrequencyEnum
    {
        return $this->frequency;
    }

    public function setFrequency(SubscriptionFrequencyEnum $frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getLastSent(): ?\DateTimeInterface
    {
        return $this->lastSent;
    }

    public function setLastSent(?\DateTimeInterface $lastSent): self
    {
        $this->lastSent = $lastSent;

        return $this;
    }
}
