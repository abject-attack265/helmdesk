<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_plans', function (Blueprint $table) {
            $table->comment('接待方案：AI 客服编排配置的容器，渠道按方案接待');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');
            $table->text('description')->nullable();

            $table->json('persona_config')->nullable()->comment('AI 人设配置：身份 / 语气等');
            $table->text('global_instructions')->nullable()->comment('全局系统指令（追加到 AI 提示词）');
            $table->json('strategy_config')->comment('模型 / 回退策略配置');

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_plans');
    }
};
