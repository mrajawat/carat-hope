<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DISPATCHED = 'dispatched';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case FAILED_ATTEMPT = 'failed_attempt';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
}
