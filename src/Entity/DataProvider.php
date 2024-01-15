<?php

namespace App\Entity;

use App\Repository\DataProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DataProviderRepository::class)]
class DataProvider extends AbstractBaseEntity
{
    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(length: 255)]
    private ?string $class = null;

    #[ORM\Column(nullable: true)]
    private ?bool $enableClientSync = null;

    #[ORM\Column(nullable: true)]
    private ?bool $enableAccountSync = null;

    #[ORM\ManyToMany(targetEntity: View::class, mappedBy: 'dataProviders')]
    private Collection $views;

    public function __construct()
    {
        $this->views = new ArrayCollection();
    }

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

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    public function isEnableClientSync(): ?bool
    {
        return $this->enableClientSync;
    }

    public function setEnableClientSync(bool $enableClientSync): static
    {
        $this->enableClientSync = $enableClientSync;

        return $this;
    }

    public function isEnableAccountSync(): ?bool
    {
        return $this->enableAccountSync;
    }

    public function setEnableAccountSync(bool $enableAccountSync): static
    {
        $this->enableAccountSync = $enableAccountSync;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getName() ?? ''.$this->getId();
    }

    /**
     * @return Collection<int, View>
     */
    public function getViews(): Collection
    {
        return $this->views;
    }

    public function addView(View $view): static
    {
        if (!$this->views->contains($view)) {
            $this->views->add($view);
            $view->addDataProvider($this);
        }

        return $this;
    }

    public function removeView(View $view): static
    {
        if ($this->views->removeElement($view)) {
            $view->removeDataProvider($this);
        }

        return $this;
    }
}
