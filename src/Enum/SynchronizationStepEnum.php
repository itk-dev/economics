<?php

namespace App\Enum;

enum SynchronizationStepEnum: string
{
    case PROJECTS = 'PROJECTS';
    case ACCOUNTS = 'ACCOUNTS';
    case ISSUES = 'ISSUES';
    case WORKLOGS = 'WORKLOGS';
}
