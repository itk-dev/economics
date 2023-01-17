<?php

namespace App\Model\SprintReport;

enum SprintStateEnum: string
{
    case ACTIVE = 'ACTIVE';
    case FUTURE = 'FUTURE';
    case OTHER = 'OTHER';
}
