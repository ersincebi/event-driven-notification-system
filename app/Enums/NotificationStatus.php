<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
}
