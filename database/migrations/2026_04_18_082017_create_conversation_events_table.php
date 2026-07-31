<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_events', function (Blueprint $table) {
            $table->comment('会话事件流：系统事件审计（转人工 / 状态变更等）');

            $table->ulid('id')->primary();
            $table->timestamp('created_at')->nullable();
            $table->ulid('conversation_id')->comment('所属会话');
            $table->ulid('actor_user_id')->nullable()->comment('触发事件的同事用户；系统事件为空');
            $table->string('type', 50)->comment('事件类型：created / assignment_changed / handoff_requested / status_changed / reception_turn_started / reception_tool_called / reception_turn_ended');
            $table->json('payload')->nullable()->comment('事件结构化负载（变更前后值、工具调用详情等）');

            $table->index(['conversation_id', 'created_at', 'id'], 'conversation_events_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_events');
    }
};
