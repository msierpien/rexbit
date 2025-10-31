<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case ERROR = 'error';
}
