<?php

namespace App\Entity;

use App\Enum\SynchronizationStatusEnum;
use App\Enum\SynchronizationStepEnum;
use App\Repository\SynchronizationJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SynchronizationJobRepository::class)]
class SynchronizationJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $started = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $ended = null;

    #[ORM\Column(nullable: true)]
    private ?int $progress = null;

    #[ORM\Column(nullable: true, enumType: SynchronizationStepEnum::class)]
    private ?SynchronizationStepEnum $step = null;

    #[ORM\Column(enumType: SynchronizationStatusEnum::class)]
    private ?SynchronizationStatusEnum $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $messages = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStarted(): ?\DateTimeInterface
    {
        return $this->started;
    }

    public function setStarted(?\DateTimeInterface $started): static
    {
        $this->started = $started;

        return $this;
    }

    public function getEnded(): ?\DateTimeInterface
    {
        return $this->ended;
    }

    public function setEnded(?\DateTimeInterface $ended): static
    {
        $this->ended = $ended;

        return $this;
    }

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function setProgress(?int $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function getStep(): ?SynchronizationStepEnum
    {
        return $this->step;
    }

    public function setStep(?SynchronizationStepEnum $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getStatus(): ?SynchronizationStatusEnum
    {
        return $this->status;
    }

    public function setStatus(SynchronizationStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMessages(): ?string
    {
        return $this->messages;
    }

    public function setMessages(?string $messages): static
    {
        $this->messages = $messages;

        return $this;
    }
}
