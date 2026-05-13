<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_can_create_notification_and_dispatch_job(): void
    {
        $payload = [
            'recipient' => '+905555555555',
            'channel' => NotificationChannel::SMS->value,
            'content' => 'test content',
            'priority' => 'high',
        ];

        $response = $this->postJson('/api/notifications', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.recipient', $payload['recipient']);

        $this->assertDatabaseHas('notifications', [
            'recipient' => $payload['recipient'],
            'channel' => $payload['channel'],
        ]);

        Queue::assertPushed(ProcessNotification::class);
    }

    public function test_returns_existing_notification_for_duplicate_idempotency_key(): void
    {
        $payload = [
            'recipient' => 'test@test.com',
            'channel' => NotificationChannel::EMAIL->value,
            'content' => 'test content',
            'idempotency_key' => 'test-idem-key-1',
        ];

        $first = $this->postJson('/api/notifications', $payload);
        $first->assertStatus(201);
        $firstId = $first->json('data.id');

        $second = $this->postJson('/api/notifications', $payload);
        $second->assertStatus(201)->assertJsonPath('data.id', $firstId);

        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(ProcessNotification::class, 1);
    }

    public function test_rejects_invalid_channel(): void
    {
        $this->postJson('/api/notifications', [
            'recipient' => '+905555555555',
            'channel' => 'fax',
            'content' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['channel']);
    }

    public function test_rejects_missing_recipient(): void
    {
        $this->postJson('/api/notifications', [
            'channel' => NotificationChannel::SMS->value,
            'content' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['recipient']);
    }

    public function test_rejects_content_over_1000_chars(): void
    {
        $this->postJson('/api/notifications', [
            'recipient' => '+905555555555',
            'channel' => NotificationChannel::SMS->value,
            'content' => str_repeat('a', 1001),
        ])->assertStatus(422)->assertJsonValidationErrors(['content']);
    }

    public function test_rejects_batch_over_1000_items(): void
    {
        $items = array_fill(0, 1001, [
            'recipient' => '+905555555555',
            'channel' => NotificationChannel::SMS->value,
            'content' => 'x',
        ]);

        $this->postJson('/api/notifications/batch', ['notifications' => $items])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notifications']);
    }

    public function test_can_cancel_pending_notification(): void
    {
        $notification = Notification::factory()->create(['status' => 'pending']);

        $this->patchJson("/api/notifications/{$notification->id}/cancel")
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => NotificationStatus::CANCELED->value,
        ]);
    }

    public function test_cannot_cancel_completed_notification(): void
    {
        $notification = Notification::factory()->create([
            'status' => NotificationStatus::COMPLETED->value,
        ]);

        $this->patchJson("/api/notifications/{$notification->id}/cancel")
            ->assertStatus(400);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => NotificationStatus::COMPLETED->value,
        ]);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/notifications/' . \Illuminate\Support\Str::uuid())
            ->assertStatus(404);
    }

    public function test_can_create_batch_notifications(): void
    {
        $payload = [
            'notifications' => [
                ['recipient' => '+905555555555', 'channel' => NotificationChannel::SMS->value, 'content' => 'a'],
                ['recipient' => 'test@test.com', 'channel' => NotificationChannel::EMAIL->value, 'content' => 'b'],
            ],
        ];

        $response = $this->postJson('/api/notifications/batch', $payload);

        $response->assertStatus(201)->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('notifications', 2);
        $batchId = Notification::first()->batch_id;
        $this->assertNotNull($batchId);

        Queue::assertPushed(ProcessNotification::class, 2);
    }

    public function test_can_show_batch(): void
    {
        $batchId = (string) \Illuminate\Support\Str::uuid();
        Notification::factory()->count(3)->create(['batch_id' => $batchId]);
        Notification::factory()->create();

        $this->getJson("/api/notifications/batch/{$batchId}")
            ->assertStatus(200)
            ->assertJsonPath('count', 3)
            ->assertJsonPath('batch_id', $batchId);
    }

    public function test_can_filter_by_channel(): void
    {
        Notification::factory()->create(['channel' => NotificationChannel::SMS->value]);
        Notification::factory()->create(['channel' => NotificationChannel::EMAIL->value]);

        $this->getJson('/api/notifications?channel=sms')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.channel', 'sms');
    }

    public function test_can_filter_by_status(): void
    {
        Notification::factory()->create(['status' => NotificationStatus::COMPLETED->value]);
        Notification::factory()->create(['status' => NotificationStatus::PENDING->value]);

        $this->getJson('/api/notifications?status=completed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', 'completed');
    }

    public function test_can_filter_by_date_range(): void
    {
        Notification::factory()->create(['created_at' => now()->subDays(5)]);
        Notification::factory()->create(['created_at' => now()->subDay()]);

        $start = now()->subDays(2)->toIso8601String();
        $end   = now()->toIso8601String();

        $this->getJson("/api/notifications?start_date={$start}&end_date={$end}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_paginates_results(): void
    {
        Notification::factory()->count(25)->create();

        $this->getJson('/api/notifications?per_page=10')
            ->assertStatus(200)
            ->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.total', 25);
    }

    public function test_observability_endpoints_return_success(): void
    {
        Redis::shouldReceive('connection->ping')->andReturn(true);
        Redis::shouldReceive('connection->llen')->andReturn(0);

        $this->getJson('/api/system/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'healthy');

        $this->getJson('/api/system/metrics')
            ->assertStatus(200)
            ->assertJsonStructure([
                'queue_depth' => ['high', 'normal', 'low'],
                'rates' => ['success_rate', 'failure_rate'],
                'latency' => ['avg_seconds_last_hour'],
                'totals' => ['all', 'completed', 'failed'],
            ]);
    }

    public function test_correlation_id_is_echoed(): void
    {
        $cid = '11111111-2222-3333-4444-555555555555';

        $response = $this->withHeaders(['X-Correlation-ID' => $cid])
            ->postJson('/api/notifications', [
                'recipient' => '+905555555555',
                'channel' => NotificationChannel::SMS->value,
                'content' => 'x',
            ]);

        $response->assertStatus(201);
        $this->assertSame($cid, $response->headers->get('X-Correlation-ID'));
        $this->assertDatabaseHas('notifications', ['correlation_id' => $cid]);
    }
}
