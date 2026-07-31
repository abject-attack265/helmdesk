<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_qa_questions', function (Blueprint $table) {
            $table->comment('问答相似问法');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('knowledge_qa_entry_id')->comment('所属问答条目');
            $table->string('question', 500)->comment('相似问法文本，与主问题指向同一答案');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序权重');

            $table->index('knowledge_qa_entry_id', 'idx_kb_qa_question_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_qa_questions');
    }
};
