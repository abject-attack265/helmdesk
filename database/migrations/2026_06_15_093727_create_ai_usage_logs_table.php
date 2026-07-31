<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->comment('LLM 调用 token 用量明细（一行=一次推理）；仅观测不计费');

            $table->ulid('id')->primary();
            $table->timestamp('created_at')->comment('调用发生时间，用于按当日 / 本月聚合');

            $table->ulid('ai_model_id')->nullable()->comment('调用所用模型，指向 ai_models.id；模型删除或流式候选缺失时为空');
            $table->string('model_name')->default('')->comment('调用时的模型名快照，便于模型行删除后仍可读');
            $table->string('purpose')->comment('调用用途：reception_chat / assistant / background_task 等');
            $table->ulid('conversation_id')->nullable()->comment('关联会话；后台助手等无会话场景为空');
            $table->unsignedInteger('input_tokens')->default(0)->comment('本次调用输入（prompt）token 数');
            $table->unsignedInteger('output_tokens')->default(0)->comment('本次调用输出（completion）token 数');

            $table->index('created_at', 'ai_usage_logs_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
