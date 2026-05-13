<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ObservabilityController extends Controller
{
    public function metrics(): JsonResponse
    {
        $prefix = config('database.redis.options.prefix', '');

        $queues = [
            'high' => (int) Redis::connection()->llen($prefix.'queues:high'),
            'normal' => (int) Redis::connection()->llen($prefix.'queues:normal'),
            'low' => (int) Redis::connection()->llen($prefix.'queues:low'),
        ];

        $total = max(Notification::count(), 1);
        $successCount = Notification::where('status', NotificationStatus::COMPLETED->value)->count();
        $failedCount = Notification::where('status', NotificationStatus::FAILED->value)->count();

        $recent = Notification::query()
            ->where('status', NotificationStatus::COMPLETED->value)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', now()->subHour())
            ->get(['created_at', 'delivered_at']);

        $latencySeconds = (float) ($recent->avg(
            fn ($n) => $n->delivered_at->diffInSeconds($n->created_at)
        ) ?? 0);

        return response()->json([
            'queue_depth' => $queues,
            'rates' => [
                'success_rate' => round(($successCount / $total) * 100, 2),
                'failure_rate' => round(($failedCount / $total) * 100, 2),
            ],
            'latency' => [
                'avg_seconds_last_hour' => round($latencySeconds, 3),
            ],
            'totals' => [
                'all' => Notification::count(),
                'completed' => $successCount,
                'failed' => $failedCount,
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $status = in_array(false, $services, true) ? 503 : 200;

        return response()->json([
            'status' => $status === 200 ? 'healthy' : 'unhealthy',
            'services' => $services,
        ], $status);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return false;
        }
    }
}
