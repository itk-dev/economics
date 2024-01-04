<?php

namespace App\Entity;

use App\Repository\PriceListRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceListRepository::class)]
class PriceList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    private ?string $name = null;

    private ?float $price = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?int
    {
        return $this->name;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }
}
