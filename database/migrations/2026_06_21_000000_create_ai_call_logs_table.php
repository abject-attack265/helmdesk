<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_call_logs', function (Blueprint $table) {
            $table->comment('LLM 调用日志：一行=一个 AI 会话（接待按 conversation 合并逐 turn 追加，其余一次运行一行）');

            $table->ulid('id')->primary();
            $table->timestamp('created_at')->comment('会话首轮请求发起时间');
            $table->timestamp('last_at')->comment('最近一轮活动时间（列表排序依据）');

            $table->string('purpose')->comment('调用用途：reception_reply / conversation_summary 等');

            $table->ulid('conversation_id')->nullable()->comment('关联会话（接待场景的合并键与最通用定位钥匙）');
            $table->ulid('conversation_message_id')->nullable()->comment('针对单条消息的调用所指向的消息');
            $table->ulid('contact_id')->nullable()->comment('联系人维度调用所指向的联系人');

            $table->string('model_name')->default('')->comment('最近一轮所用模型名快照');
            $table->string('status')->default('success')->comment('success / error：任一条目失败即 error');
            $table->text('error_message')->nullable()->comment('首个出错条目的错误信息');
            $table->unsignedInteger('duration_ms')->default(0)->comment('各条目耗时之和（毫秒）');
            $table->unsignedInteger('input_tokens')->default(0)->comment('各条目输入 token 之和');
            $table->unsignedInteger('output_tokens')->default(0)->comment('各条目输出 token 之和');
            $table->unsignedInteger('turn_count')->default(0)->comment('轮次数（按 turn 计）');

            $table->json('system_prompts')->comment('最近一轮的 system prompt 快照列表（接待含历史上下文第二条）');
            $table->json('available_tools')->comment('最近一轮提供给模型的工具定义快照：[{name, description}]');
            $table->json('messages')->comment('对话时间线：user / assistant 条目，assistant 内按序内嵌 text 与 tool_call 分段');

            $table->string('reply_preview', 512)->default('')->comment('最近一条 AI 回复的纯文本预览（列表用）');
            $table->text('search_text')->default('');

            $table->index('created_at', 'ai_call_logs_time_idx');
            $table->index('last_at', 'ai_call_logs_last_at_idx');
            $table->index(['purpose', 'conversation_id'], 'ai_call_logs_purpose_conversation_idx');
            $table->index('conversation_message_id', 'ai_call_logs_message_idx');
            $table->index('contact_id', 'ai_call_logs_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_logs');
    }
};
