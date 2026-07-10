<?php

namespace App\Enums;

enum CarrierType: string
{
    case MANUAL = 'manual';
    case SHIPROCKET = 'shiprocket';
    case DIRECT_CARRIER = 'direct_carrier';
}
