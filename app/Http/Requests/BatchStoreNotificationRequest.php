<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchStoreNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notifications' => ['required', 'array', 'max:1000'],
            'notifications.*.recipient' => ['required', 'string', 'max:255'],
            'notifications.*.channel' => ['required', Rule::enum(NotificationChannel::class)],
            'notifications.*.content' => ['required', 'string', 'max:1000'],
            'notifications.*.priority' => ['nullable', Rule::enum(NotificationPriority::class)],
            'notifications.*.idempotency_key' => ['nullable', 'string', 'distinct'],
        ];
    }
}
