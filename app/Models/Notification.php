<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'batch_id',
        'recipient',
        'channel',
        'content',
        'priority',
        'status',
        'idempotency_key',
        'external_id',
        'error_message',
        'correlation_id',
        'processed_at',
        'delivered_at',
    ];

    protected $attributes = [
        'priority' => 'normal',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'priority' => NotificationPriority::class,
            'status' => NotificationStatus::class,
            'processed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
