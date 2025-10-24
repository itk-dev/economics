<?php

namespace App\Enum;

enum HostingProviderEnum: string
{
    case ADM = 'ADM';
    case DMZ = 'DMZ';
    case HETZNER = 'HETZNER';
}
