<?php

namespace App\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait SynchronizedEntityTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fetchDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sourceModifiedDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sourceDeletedDate = null;

    public function getFetchDate(): ?\DateTimeInterface
    {
        return $this->fetchDate;
    }

    public function setFetchDate(?\DateTimeInterface $fetchDate): void
    {
        $this->fetchDate = $fetchDate;
    }

    public function getSourceModifiedDate(): ?\DateTimeInterface
    {
        return $this->sourceModifiedDate;
    }

    public function setSourceModifiedDate(?\DateTimeInterface $sourceModifiedDate): void
    {
        $this->sourceModifiedDate = $sourceModifiedDate;
    }

    public function getSourceDeletedDate(): ?\DateTimeInterface
    {
        return $this->sourceDeletedDate;
    }

    public function setSourceDeletedDate(?\DateTimeInterface $sourceDeletedDate): void
    {
        $this->sourceDeletedDate = $sourceDeletedDate;
    }
}
