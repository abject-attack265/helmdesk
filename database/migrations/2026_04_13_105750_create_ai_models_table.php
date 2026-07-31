<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->comment('全局 AI 供应商下的模型（一行=一个模型+一个用途；type：llm / embedding / rerank）');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('ai_provider_id')->comment('所属 AI 供应商，指向 ai_providers.id');
            $table->string('model_id')->comment('供应商侧的模型标识，调用时下发给上游 API，如 gpt-4o');
            $table->string('name');
            $table->string('type')->comment('模型能力类型：llm / embedding / rerank');
            $table->string('purpose')->comment('单一运行时用途；同 model 多用途拆成多行');
            $table->boolean('is_active')->default(true)->comment('是否启用，停用后不参与运行时取用');
            $table->unsignedInteger('weight')->default(1)->comment('同用途内加权随机选择的权重，1-100，值越大被选中概率越高');
            $table->boolean('supports_image_input')->default(false)->comment('是否支持图片内容块输入');
            $table->boolean('supports_video_input')->default(false)->comment('是否支持视频内容块输入');

            $table->unique(['ai_provider_id', 'model_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
