<?php

namespace App\Enum;

enum ServerSizeEnum: string
{
    case LILLE = 'lille';
    case MELLEM = 'mellem';
    case STOR = 'stor';
    case CUSTOM = 'custom';
}
