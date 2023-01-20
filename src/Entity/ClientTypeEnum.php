<?php

namespace App\Entity;

enum ClientTypeEnum: string
{
    case INTERNAL = 'Internal';
    case EXTERNAL = 'External';
}
