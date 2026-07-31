<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_attribute_values', function (Blueprint $table) {
            $table->comment('联系人自定义属性值');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('contact_id')->comment('所属联系人 contacts.id');
            $table->ulid('definition_id')->comment('属性定义 attribute_definitions.id');
            $table->json('value_json')->comment('属性值：按属性类型存储的结构化值（标量/数组）');
            $table->string('source', 20)->default('manual')->comment('赋值来源：manual 人工 / api / import / workflow / ai / merge / channel');
            $table->float('confidence')->nullable()->comment('置信度：AI 赋值时的可信度评分');
            $table->ulid('updated_by_user_id')->nullable()->comment('最后更新人 user_id：系统/AI 来源时为空');

            $table->unique(['contact_id', 'definition_id']);
            $table->index('contact_id');
            $table->index('definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_attribute_values');
    }
};
