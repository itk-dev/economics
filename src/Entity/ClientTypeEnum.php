<?php

namespace App\Entity;

enum ClientTypeEnum: string
{
    case INTERNAL = 'INTERNAL';
    case EXTERNAL = 'EXTERNAL';
}
