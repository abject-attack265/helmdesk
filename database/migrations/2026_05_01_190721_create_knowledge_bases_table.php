<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->comment('知识库：文档型或问答型的检索库');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->string('name');
            $table->string('category')->default('standard')->comment('知识库类型：standard 文档 / qa 问答 / wechat_public 公众号');
            $table->ulid('avatar_id')->nullable()->comment('知识库头像附件');
            $table->text('description')->nullable()->comment('知识库描述');

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};
