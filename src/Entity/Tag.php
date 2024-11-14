<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column]
    private ?int $projectId = null;

    #[ORM\Column]
    private ?int $ticketId = null;

    #[ORM\Column]
    private ?bool $isBillable = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
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

    public function getTicketId(): ?int
    {
        return $this->ticketId;
    }

    public function setTicketId(int $ticketId): static
    {
        $this->ticketId = $ticketId;

        return $this;
    }

    public function isBillable(): ?bool
    {
        return $this->isBillable;
    }

    public function setBillable(bool $isBillable): static
    {
        $this->isBillable = $isBillable;

        return $this;
    }
}
