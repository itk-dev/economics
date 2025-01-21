<?php

namespace App\Enum;

enum SynchronizationStatusEnum: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case RUNNING = 'RUNNING';
    case DONE = 'DONE';
    case ERROR = 'ERROR';
}
