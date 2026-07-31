<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_providers', function (Blueprint $table) {
            $table->comment('系统翻译供应商配置，运行时按池轮询');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->string('slug')->unique()->comment('供应商唯一标识，按名称生成');
            $table->string('name')->unique()->comment('展示名称，用于区分同协议的多个供应商');
            $table->string('protocol')->comment('翻译协议标识，决定凭据字段与 driver 实现');
            $table->string('icon')->nullable()->comment('图标标识或 URL');
            $table->text('credentials')->nullable()->comment('加密存储的凭据 JSON');
            $table->json('credential_fields')->comment('凭据表单字段定义，用于动态渲染设置页');
            $table->json('options')->nullable()->comment('供应商非敏感运行参数');
            $table->boolean('is_active')->default(true)->comment('是否启用：仅启用且凭据完整的供应商进入运行时轮询池');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_providers');
    }
};
