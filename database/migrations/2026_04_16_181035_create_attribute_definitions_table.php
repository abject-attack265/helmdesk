<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->comment('自定义属性定义：应用可配置的联系人属性元数据');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->string('key', 50)->comment('属性键名：应用内唯一，程序化引用标识');
            $table->string('name', 100)->comment('属性显示名称');
            $table->string('description', 255)->nullable()->comment('属性说明');
            $table->string('type', 30)->comment('属性类型：text / textarea / number / date / boolean / single_select / multi_select');
            $table->json('config')->nullable()->comment('类型配置：如单选/多选的可选项列表、数值范围等');
            $table->unsignedInteger('display_order')->default(0)->comment('展示排序，越小越靠前');
            $table->boolean('is_filterable')->default(false)->comment('是否可用于列表筛选');
            $table->boolean('is_api_writable')->default(true)->comment('是否允许通过 API 写入');
            $table->boolean('is_ai_readable')->default(true)->comment('是否允许 AI 读取');
            $table->boolean('is_ai_writable')->default(false)->comment('是否允许 AI 写入');
            $table->softDeletes();

            $table->index(['deleted_at', 'display_order']);
        });

        // 属性键唯一，仅约束未软删行；删除后允许同名 key 复用。
        DB::statement(
            'CREATE UNIQUE INDEX attribute_definitions_key_active_unique ON attribute_definitions (key) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_definitions');
    }
};
