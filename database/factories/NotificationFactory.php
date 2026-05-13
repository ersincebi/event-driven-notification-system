<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient' => $this->faker->phoneNumber(),
            'channel'   => NotificationChannel::SMS->value,
            'content'   => $this->faker->sentence(),
            'priority'  => NotificationPriority::NORMAL->value,
            'status'    => NotificationStatus::PENDING->value,
        ];
    }
}
