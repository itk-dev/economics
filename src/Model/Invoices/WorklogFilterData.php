<?php

namespace App\Model\Invoices;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Entity\Worker;

class WorklogFilterData
{
    public ?string $search = null;
    public ?\DateTime $periodFrom = null;
    public ?\DateTime $periodTo = null;
    public ?Worker $worker = null;
    public ?Project $project = null;
    public ?DataProvider $dataProvider = null;
    public ?bool $isBilled = null;
}
