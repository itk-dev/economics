<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;

class Versions
{
    /** @var ArrayCollection<string, VersionModel> */
    public ArrayCollection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }
}
