<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_groups', function (Blueprint $table) {
            $table->comment('知识库文档分组');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('knowledge_base_id')->comment('所属知识库');
            $table->ulid('parent_id')->nullable()->comment('父分组，支持树形层级');
            $table->string('name');
            $table->boolean('is_default')->default(false)->comment('是否为默认分组（未归类文档归入此处）');
            $table->unsignedInteger('sort_order')->default(0)->comment('同级排序权重');

            $table->index('parent_id');
            $table->index(['knowledge_base_id', 'is_default'], 'idx_kb_group_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_groups');
    }
};
