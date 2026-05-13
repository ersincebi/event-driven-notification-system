<?php

namespace App\Services;

use App\Models\Notification;
use App\Enums\NotificationStatus;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessNotification;

class NotificationService
{
    public function createNotification(array $data, ?string $batchId = null): Notification
    {
        if (!empty($data['idempotency_key'])) {
            $existing = Notification::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $data['batch_id'] = $batchId;
        $data['correlation_id'] = $data['correlation_id'] ?? request()?->header('X-Correlation-ID');

        $notification = Notification::create($data);

        ProcessNotification::dispatch($notification);

        return $notification;
    }

    public function createBatchNotifications(array $notificationsData): Collection
    {
        $batchId = Str::uuid()->toString();

        return DB::transaction(function () use ($notificationsData, $batchId) {
            $created = collect();
            foreach ($notificationsData as $data) {
                $created->push($this->createNotification($data, $batchId));
            }
            return $created;
        });
    }

    public function cancelNotification(Notification $notification): bool
    {
        $updated = Notification::where('id', $notification->id)
            ->where('status', NotificationStatus::PENDING->value)
            ->update(['status' => NotificationStatus::CANCELED->value]);

        return $updated > 0;
    }

    public function listNotifications(array $filters): LengthAwarePaginator
    {
        $query = Notification::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        return $query->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 15));
    }
}
