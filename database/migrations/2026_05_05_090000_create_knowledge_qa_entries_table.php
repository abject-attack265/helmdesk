<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_qa_entries', function (Blueprint $table) {
            $table->comment('知识库问答条目：主问题 + 相似问 + 答案的容器');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('knowledge_base_id')->comment('所属知识库');
            $table->ulid('group_id')->comment('所属分组');
            $table->ulid('created_by_user_id')->nullable()->comment('创建者用户');

            $table->string('question', 500)->comment('主问题文本');
            $table->string('status', 32)->default('pending')->comment('索引状态：pending/indexing/indexed/failed');
            $table->text('error_message')->nullable()->comment('索引失败原因');
            $table->string('vector_status', 32)->default('idle')->comment('向量索引状态：idle/pending/processing/succeeded/failed');
            $table->text('vector_error')->nullable()->comment('向量索引失败原因');
            $table->timestamp('vector_indexed_at')->nullable()->comment('向量索引完成时间');
            $table->unsignedInteger('sort_order')->default(0)->comment('同分组内排序权重');

            $table->index(['knowledge_base_id', 'group_id'], 'idx_kb_qa_entry_kb_group');
            $table->index(['knowledge_base_id', 'status'], 'idx_kb_qa_entry_kb_status');
            $table->index('created_at', 'idx_kb_qa_entry_created_at');
            $table->index(['knowledge_base_id', 'question'], 'idx_kb_qa_entry_kb_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_qa_entries');
    }
};
