<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->comment('全局 AI 供应商（系统级，协议 + 凭据，跨应用共享）');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->string('brand')->comment('品牌目录标识，如 openai / deepseek / qwen / azure / ollama，决定预设 base_url 和图标');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('protocol')->comment('底层调用协议：openai / anthropic / gemini，各品牌最终映射到这三种原生通道之一');
            $table->string('icon')->nullable()->comment('图标标识或 URL，缺省时按 brand 取默认图标');
            $table->text('credentials')->nullable()->comment('加密存储的凭据 JSON（api_key、base_url 等）');
            $table->json('credential_fields')->comment('凭据表单字段定义：field/label/secret 等，用于动态渲染设置页');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
