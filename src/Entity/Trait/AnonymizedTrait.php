<?php

namespace App\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait AnonymizedTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $anonymizedDate = null;

    public function getAnonymizedDate(): ?\DateTimeInterface
    {
        return $this->anonymizedDate;
    }

    public function setAnonymizedDate(?\DateTimeInterface $anonymizedDate): void
    {
        $this->anonymizedDate = $anonymizedDate;
    }
}
