<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 创建 Telegram 入站 Update 处理台账。 */
    public function up(): void
    {
        Schema::create('telegram_inbound_updates', function (Blueprint $table) {
            $table->comment('Telegram 入站 Update 可靠处理台账');
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('channel_id');
            $table->string('provider_update_id', 64);
            $table->string('update_type', 32);
            $table->json('payload');
            $table->string('gateway_external_id')->nullable();
            $table->string('gateway_external_email')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->unique(['channel_id', 'provider_update_id'], 'telegram_inbound_channel_update_unique');
            $table->index(['status', 'available_at', 'locked_at'], 'telegram_inbound_dispatch_idx');
        });
    }

    /** 删除 Telegram 入站 Update 处理台账。 */
    public function down(): void
    {
        Schema::dropIfExists('telegram_inbound_updates');
    }
};
