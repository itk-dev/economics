<?php

namespace App\Entity\Trait;

use App\Entity\DataProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait FetchDateTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fetchTime = null;

    public function getFetchTime(): ?\DateTimeInterface
    {
        return $this->fetchTime;
    }

    public function setFetchTime(?\DateTimeInterface $fetchTime): void
    {
        $this->fetchTime = $fetchTime;
    }
}
