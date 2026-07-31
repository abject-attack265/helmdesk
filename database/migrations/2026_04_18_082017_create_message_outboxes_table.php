<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 创建外部渠道消息投递台账。
     */
    public function up(): void
    {
        Schema::create('message_outboxes', function (Blueprint $table) {
            $table->comment('外部渠道消息可靠投递台账');
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('conversation_message_id')->unique();
            $table->ulid('channel_id');
            $table->string('channel_type', 32);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload')->nullable();

            $table->index(['status', 'available_at', 'locked_at'], 'message_outboxes_dispatch_idx');
            $table->index(['channel_type', 'status', 'created_at'], 'message_outboxes_channel_status_idx');
        });
    }

    /**
     * 删除外部渠道消息投递台账。
     */
    public function down(): void
    {
        Schema::dropIfExists('message_outboxes');
    }
};
