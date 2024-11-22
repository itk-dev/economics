<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

class IssueEpic extends AbstractBaseEntity
{
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $projectTrackerId = null;

}
