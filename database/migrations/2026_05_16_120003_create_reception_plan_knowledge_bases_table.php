<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_plan_knowledge_bases', function (Blueprint $table) {
            $table->comment('接待方案与知识库的关联：任务智能体运行时可检索的知识库范围');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('reception_plan_id');
            $table->ulid('knowledge_base_id');

            $table->unique(['reception_plan_id', 'knowledge_base_id'], 'uniq_plan_knowledge_base');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_plan_knowledge_bases');
    }
};
