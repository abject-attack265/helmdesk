<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_extractions', function (Blueprint $table) {
            $table->comment('经验提炼运行：管理员筛选并勾选一批人工会话后触发，LLM 批量分析产出候选经验');

            $table->ulid('id')->primary();
            $table->ulid('triggered_by_user_id')->nullable();
            $table->ulid('knowledge_base_id')->nullable()->comment('绑定的问答知识库，采纳的候选经验落入该库');
            $table->string('status')->comment('running / completed / failed');
            $table->timestamp('scanned_from')->nullable()->comment('所选会话中最早的关闭时间（展示用）');
            $table->timestamp('scanned_until')->nullable()->comment('所选会话中最晚的关闭时间（展示用），也是下次筛选的默认起点');
            $table->unsignedInteger('conversation_count')->default(0)->comment('本次实际送入提炼的会话数');
            $table->unsignedInteger('candidate_count')->default(0)->comment('本次产出的候选经验数');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at', 'experience_extractions_time_idx');
            $table->index(['knowledge_base_id', 'created_at'], 'experience_extractions_kb_time_idx');
        });

        Schema::create('experience_extraction_conversations', function (Blueprint $table) {
            $table->comment('提炼运行消费的会话清单：支撑「已提炼过」标记与溯源');

            $table->ulid('extraction_id');
            $table->ulid('conversation_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['extraction_id', 'conversation_id'], 'experience_extraction_conversations_pk');
            $table->index('conversation_id', 'experience_extraction_conversations_conversation_idx');
        });

        Schema::create('experience_candidates', function (Blueprint $table) {
            $table->comment('候选经验：提炼运行的产出，管理员润色后采纳为知识库 QA 问答对或丢弃');

            $table->ulid('id')->primary();
            $table->ulid('extraction_id');
            $table->string('question', 500)->comment('主问题（访客情境的问句化）');
            $table->json('similar_questions')->comment('相似问法列表');
            $table->text('answer')->comment('人工处理方式提炼出的答复');
            $table->json('source_conversation_ids')->comment('支撑该候选的来源会话 ID 列表（溯源）');
            $table->unsignedInteger('conversation_count')->default(1)->comment('支撑该候选的会话数（同类问题热度）');
            $table->string('status')->comment('pending / adopted / discarded');
            $table->ulid('adopted_qa_entry_id')->nullable()->comment('采纳后生成的知识库 QA 条目');
            $table->ulid('handled_by_user_id')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'experience_candidates_status_idx');
            $table->index('extraction_id', 'experience_candidates_extraction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_extraction_conversations');
        Schema::dropIfExists('experience_candidates');
        Schema::dropIfExists('experience_extractions');
    }
};
