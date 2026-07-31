<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_tools', function (Blueprint $table) {
            $table->comment('集成同步下来的工具缓存');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('integration_id')->comment('所属集成，指向 integrations.id');
            $table->string('name');
            $table->text('description')->nullable()->comment('工具描述，由远端返回');
            $table->json('input_schema')->nullable()->comment('工具入参的 JSON Schema 定义');
            $table->json('annotations')->nullable()->comment('工具注解元数据（如只读 / 破坏性等提示）');
            $table->boolean('is_enabled')->default(true)->comment('是否启用，已下线工具会被强制置 false');
            $table->timestamp('last_seen_at')->nullable()->comment('最近一次同步时仍存在于远端的时间');
            $table->timestamp('removed_at')->nullable()->comment('远端不再返回该工具的时间，标记已下线');

            $table->unique(['integration_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tools');
    }
};
