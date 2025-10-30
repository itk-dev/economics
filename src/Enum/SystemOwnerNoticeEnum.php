<?php

namespace App\Enum;

enum SystemOwnerNoticeEnum: string
{
    case ON_SERVER = 'on_server';
    case ON_UPDATE = 'on_update';
    case NEVER = 'never';
}
