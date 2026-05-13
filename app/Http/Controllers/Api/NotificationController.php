<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchStoreNotificationRequest;
use App\Http\Requests\StoreNotificationRequest;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->createNotification($request->validated());

        return response()->json([
            'message' => 'api notification created',
            'data' => $notification,
        ], 201);
    }

    public function storeBatch(BatchStoreNotificationRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->createBatchNotifications(
            $request->validated('notifications')
        );

        return response()->json([
            'message' => 'api batch created',
            'data' => $notifications,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);

        return response()->json([
            'data' => $notification,
        ]);
    }

    public function showBatch(string $batchId): JsonResponse
    {
        $notifications = Notification::where('batch_id', $batchId)->get();

        if ($notifications->isEmpty()) {
            return response()->json(['error' => 'Batch not found'], 404);
        }

        return response()->json([
            'batch_id' => $batchId,
            'count' => $notifications->count(),
            'data' => $notifications,
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $canceled = $this->notificationService->cancelNotification($notification);

        if (! $canceled) {
            return response()->json([
                'error' => 'api cannot cancel notification',
            ], 400);
        }

        return response()->json([
            'message' => 'api notification canceled',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'channel', 'start_date', 'end_date', 'per_page']);
        $notifications = $this->notificationService->listNotifications($filters);

        return response()->json([
            'data' => $notifications,
        ]);
    }
}
