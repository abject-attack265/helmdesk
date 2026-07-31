<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->comment('联系人：访客 / 客户档案');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('type')->default('visitor')->comment('联系人类型：visitor 访客 / contact 客户');
            $table->string('source')->default('web')->comment('来源渠道：web / email / api / manual / telegram / wechat_oa');
            $table->string('name')->nullable();
            $table->text('avatar_url')->default('/images/default-avatar.svg');
            $table->timestamp('avatar_synced_at')->nullable()->comment('头像最近一次同步时间');
            $table->string('locale')->nullable()->comment('语言偏好');
            $table->string('timezone')->nullable()->comment('时区');
            $table->string('country')->nullable()->comment('国家');
            $table->string('city')->nullable()->comment('城市');
            $table->string('primary_email')->nullable()->comment('主邮箱');
            $table->string('primary_phone')->nullable()->comment('主手机号');
            $table->json('ai_context')->nullable()->comment('AI 上下文摘要：供 AI 接待引用的结构化画像数据');
            $table->text('note')->nullable()->comment('内部备注');
            $table->boolean('is_important')->default(false)->comment('是否标记为重点联系人');
            $table->timestamp('important_at')->nullable()->comment('标记重点的时间');
            $table->ulid('important_by_user_id')->nullable()->comment('标记重点的操作人 user_id');
            $table->string('important_source', 20)->nullable()->comment('重点标记来源：manual 人工 / ai 等');
            $table->timestamp('last_seen_at')->nullable()->comment('最近活跃时间');

            $table->index('type');
            $table->index(['is_important', 'last_seen_at'], 'contacts_important_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
