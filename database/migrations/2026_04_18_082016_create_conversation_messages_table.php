<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->comment('会话消息：访客 / AI / 客服的逐条消息');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('conversation_id')->comment('所属会话');
            $table->ulid('sender_user_id')->nullable()->comment('发送者同事用户；访客 / AI 消息为空');
            $table->string('sender_name')->default('')->comment('发送者展示名快照');
            $table->string('role', 20)->comment('发送方角色：visitor / ai / teammate / tool');
            $table->string('kind', 20)->comment('消息类型：text / image / file / summary / tool_call / tool_result');
            $table->text('content')->nullable()->comment('文本内容');
            $table->json('payload')->nullable()->comment('结构化负载：图片 / 文件 / 工具调用参数与结果');
            $table->float('confidence')->nullable()->comment('AI 回复置信度');
            $table->ulid('reception_plan_version_id')->nullable()->comment('驱动该 AI 回复的接待方案版本快照（仅 AI 消息，逐消息审计用）');
            $table->string('turn_id')->nullable()->comment('产生该 AI 回复的接待轮次 ID，关联 ai_call_logs.turn_id（仅 AI 接待回复）');
            $table->string('client_msg_id', 64)->nullable()->comment('客户端幂等键，弱网重发去重');
            $table->unsignedBigInteger('seq_no')->comment('会话内单调序号，前端排序 / 去重 / 补洞用');
            $table->string('delivery_status', 20)->default('sent')->comment('投递状态：sending / sent / failed');
            $table->ulid('quoted_message_id')->nullable()->comment('引用回复指向的消息');
            $table->timestamp('recalled_at')->nullable()->comment('撤回时间');
            $table->string('content_locale', 20)->nullable()->comment('消息原文语言');

            $table->index(['conversation_id', 'created_at', 'id'], 'conversation_messages_timeline_idx');
            $table->unique(['conversation_id', 'seq_no'], 'conversation_messages_seq_unique');
            $table->index('quoted_message_id', 'conversation_messages_quoted_idx');
            $table->index('turn_id', 'conversation_messages_turn_idx');
        });

        DB::statement(
            'CREATE UNIQUE INDEX conversation_messages_client_msg_unique '.
            'ON conversation_messages (conversation_id, client_msg_id) '.
            'WHERE client_msg_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS conversation_messages_client_msg_unique');
        Schema::dropIfExists('conversation_messages');
    }
};
