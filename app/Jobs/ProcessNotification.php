<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 25;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Notification $notification
    ) {
        $this->onQueue($this->notification->priority->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::withContext([
            'correlation_id' => $this->notification->correlation_id,
            'notification_id' => $this->notification->id,
            'channel' => $this->notification->channel->value,
        ]);

        $fresh = $this->notification->fresh();
        if ($fresh === null || $fresh->status === NotificationStatus::CANCELED) {
            return;
        }

        $throttleKey = 'channel:'.$this->notification->channel->value;

        Redis::throttle($throttleKey)
            ->allow(1000)
            ->every(1)
            ->then(
                fn () => $this->processDelivery(),
                fn () => $this->release(1),
            );
    }

    private function processDelivery(): void
    {
        $this->notification->update([
            'status' => NotificationStatus::PROCESSING,
            'processed_at' => now(),
        ]);

        $webhookUrl = config('services.webhook.url');

        try {
            $response = Http::timeout(5)->post($webhookUrl, [
                'to' => $this->notification->recipient,
                'channel' => $this->notification->channel->value,
                'content' => $this->notification->content,
            ]);

            if ($response->status() === 202) {
                $this->notification->update([
                    'status' => NotificationStatus::COMPLETED,
                    'external_id' => $response->json('messageId'),
                    'delivered_at' => now(),
                ]);

                return;
            }

            $this->handleFailure('Invalid response status: '.$response->status());
        } catch (\Throwable $e) {
            $this->handleFailure($e->getMessage());
        }
    }

    private function handleFailure(string $errorMessage): void
    {
        Log::error('DeliveryFailed', [
            'error' => $errorMessage,
            'attempt' => $this->attempts(),
        ]);

        $this->notification->update(['error_message' => $errorMessage]);

        if ($this->attempts() >= $this->tries) {
            $this->notification->update(['status' => NotificationStatus::FAILED]);

            return;
        }

        $this->release(pow(2, $this->attempts()) * 5);
    }
}
