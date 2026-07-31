<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_replies', function (Blueprint $table) {
            $table->comment('常用回复：客服快捷短语');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();

            $table->ulid('user_id')->nullable()->comment('归属用户；NULL 为共享，非 NULL 为该用户私有');

            $table->string('name', 120);
            $table->string('shortcut', 32)->nullable()->comment('快捷输入触发词');
            $table->text('content')->comment('回复正文');

            $table->unsignedBigInteger('usage_count')->default(0)->comment('被使用次数');
            $table->timestamp('last_used_at')->nullable()->comment('最近一次使用时间');

            $table->json('metadata')->nullable()->comment('扩展元数据：embedding、AI 生成标记等');

            $table->ulid('created_by_user_id')->nullable()->comment('创建者用户');
            $table->ulid('updated_by_user_id')->nullable()->comment('最近更新者用户');

            $table->index('user_id');
            $table->index('last_used_at');
        });

        DB::statement(
            'CREATE UNIQUE INDEX uniq_canned_replies_shortcut '
            ."ON canned_replies (COALESCE(user_id, ''), shortcut) "
            .'WHERE deleted_at IS NULL AND shortcut IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_replies');
    }
};
