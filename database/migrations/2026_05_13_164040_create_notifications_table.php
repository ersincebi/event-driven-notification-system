<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable()->index();
            $table->string('recipient');
            $table->string('channel', 20);
            $table->text('content');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key')->unique()->nullable();
            $table->string('external_id')->nullable();
            $table->text('error_message')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'channel', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
