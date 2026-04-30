<?php

namespace App\Enum;

enum SystemOwnerNoticeEnum: string
{
    case SERVERFLYTNING = 'serverflytning';
    case SIKKERHEDSPATCH = 'sikkerhedspatch';
    case CYBERSIKKERSHEDSOPDATERING = 'cybersikkershedsopdatering';
}
