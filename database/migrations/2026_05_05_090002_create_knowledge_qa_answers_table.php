<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_qa_answers', function (Blueprint $table) {
            $table->comment('问答答案');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('knowledge_qa_entry_id')->comment('所属问答条目');
            $table->longText('answer')->comment('答案正文');
            $table->boolean('is_default')->default(false)->comment('是否为默认答案');
            $table->boolean('is_enabled')->default(true)->comment('是否启用，禁用后不参与召回');
            $table->unsignedInteger('sort_order')->default(0)->comment('同条目内排序权重');

            $table->index('knowledge_qa_entry_id', 'idx_kb_qa_answer_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_qa_answers');
    }
};
