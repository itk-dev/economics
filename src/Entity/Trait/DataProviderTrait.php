<?php

namespace App\Entity\Trait;

use App\Entity\DataProvider;
use Doctrine\ORM\Mapping as ORM;

trait DataProviderTrait
{
    #[ORM\ManyToOne(targetEntity: DataProvider::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?DataProvider $dataProvider = null;

    public function getDataProvider(): ?DataProvider
    {
        return $this->dataProvider;
    }

    public function setDataProvider(?DataProvider $dataProvider): void
    {
        $this->dataProvider = $dataProvider;
    }
}
