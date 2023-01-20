<?php

namespace App\Entity;

enum MaterialNumberEnum: string
{
    case NONE = '';
    case INTERNAL = '103361';
    case EXTERNAL_WITH_MOMS = '100006';
    case EXTERNAL_WITHOUT_MOMS = '100008';
}
