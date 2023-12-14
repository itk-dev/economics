<?php

namespace App\Entity;

use App\Repository\ProjectTrackerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectTrackerRepository::class)]
class ProjectTracker extends AbstractBaseEntity
{
    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $basicAuth = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getBasicAuth(): ?string
    {
        return $this->basicAuth;
    }

    public function setBasicAuth(?string $basicAuth): static
    {
        $this->basicAuth = $basicAuth;

        return $this;
    }
}
