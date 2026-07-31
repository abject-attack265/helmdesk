<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_activity_logs', function (Blueprint $table) {
            $table->comment('联系人活动日志');

            $table->ulid('id')->primary();
            $table->timestamp('created_at')->nullable();
            $table->ulid('contact_id')->comment('日志所属联系人 contacts.id');
            $table->ulid('actor_user_id')->nullable()->comment('操作人 user_id：系统/AI 行为时为空');
            $table->string('action')->comment('动作类型：created / updated / deleted / restored / identity_added / merged_into_current / tag_attached / important_marked 等');
            $table->json('payload')->nullable()->comment('动作附加数据：记录变更详情等结构化上下文');

            $table->index(['contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_activity_logs');
    }
};
