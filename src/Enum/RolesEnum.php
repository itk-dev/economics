<?php

namespace App\Enum;

enum RolesEnum: string
{
    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN';
    case ROLE_INVOICE = 'ROLE_INVOICE';
    case ROLE_PROJECT_BILLING = 'ROLE_PROJECT_BILLING';
    case ROLE_PLANNING = 'ROLE_PLANNING';
    case ROLE_REPORT = 'ROLE_REPORT';
    case ROLE_PRODUCT_MANAGER = 'ROLE_PRODUCT_MANAGER';
}
