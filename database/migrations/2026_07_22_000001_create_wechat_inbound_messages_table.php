<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 创建微信公众号入站消息处理台账。 */
    public function up(): void
    {
        Schema::create('wechat_inbound_messages', function (Blueprint $table) {
            $table->comment('微信公众号入站消息可靠处理台账');
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('channel_id');
            $table->string('provider_message_id', 128);
            $table->string('message_type', 32);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->unique(['channel_id', 'provider_message_id'], 'wechat_inbound_channel_message_unique');
            $table->index(['status', 'available_at', 'locked_at'], 'wechat_inbound_dispatch_idx');
        });
    }

    /** 删除微信公众号入站消息处理台账。 */
    public function down(): void
    {
        Schema::dropIfExists('wechat_inbound_messages');
    }
};
