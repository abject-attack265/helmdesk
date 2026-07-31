<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('conversation_id')->comment('创建线程时所在的客户会话');

            $table->index('conversation_id', 'ai_assistant_threads_conversation_idx');
        });

        Schema::create('ai_assistant_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('thread_id')->comment('所属 AI 助手线程');
            $table->uuid('round_id')->comment('一轮问答的幂等标识');
            $table->string('role', 20)->comment('消息角色：user / assistant');
            $table->text('content')->nullable()->comment('消息正文；纯附件提问或待生成回答可为空');
            $table->json('segments')->nullable()->comment('助手回答的有序片段：text / tool_call / tool_result');
            $table->json('attachment_ids')->nullable()->comment('客服提问携带的附件 ID 列表');
            $table->ulid('sender_user_id')->nullable()->comment('发起提问的客服；助手消息为空');
            $table->string('status', 20)->comment('生成状态：pending / completed / failed');

            $table->index(['thread_id', 'created_at', 'id'], 'ai_assistant_messages_timeline_idx');
            $table->unique(['thread_id', 'round_id', 'role'], 'ai_assistant_messages_round_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_threads');
    }
};
