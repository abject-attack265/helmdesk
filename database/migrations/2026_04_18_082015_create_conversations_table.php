<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->comment('会话：一次访客接待的主体，挂联系人 / 渠道 / 接待方案版本');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('contact_id')->nullable()->comment('关联联系人；匿名访客可为空');
            $table->ulid('assigned_user_id')->nullable()->comment('当前接管的同事用户');
            $table->ulid('channel_id')->nullable()->comment('接入渠道');
            $table->ulid('reception_plan_version_id')->nullable()->comment('本会话锁定的接待方案版本快照');
            $table->string('entry_mode', 20)->nullable()->comment('访客进入模式：standalone / widget 等');
            $table->string('visitor_locale', 10)->default('zh-CN')->comment('访客界面语言');
            $table->string('source', 20)->default('manual')->comment('会话来源：manual / 渠道自动创建等');
            $table->string('status', 20)->default('open')->comment('会话状态：open / closed');
            $table->string('inbox_status', 30)->default('ai_handling')->comment('接待状态：ai_handling / teammate_pending / teammate_handling');
            $table->boolean('waiting_for_visitor_reply')->default(false)->comment('是否在等访客回复');
            $table->string('subject')->nullable();
            $table->text('summary')->nullable()->comment('AI 生成的会话摘要');
            $table->string('summary_locale', 20)->nullable()->comment('摘要原文语言');
            $table->unsignedBigInteger('summary_last_message_seq_no')->default(0)->comment('摘要覆盖到的最后消息序号');
            $table->timestamp('summary_generated_at')->nullable()->comment('摘要最近生成时间');
            $table->json('ai_context')->nullable()->comment('AI 滚动上下文（事实点等），供联系人级摘要吸收');
            $table->json('channel_context')->nullable()->comment('渠道上下文快照');
            $table->string('last_message_preview')->nullable()->comment('最后一条消息预览文本');
            $table->timestamp('last_message_at')->nullable()->comment('最后一条消息时间，列表排序用');
            $table->unsignedInteger('unread_visitor_message_count')->default(0)->comment('未读访客消息数');
            $table->unsignedInteger('unread_agent_message_count')->default(0)->comment('未读同事消息数');
            $table->unsignedBigInteger('next_seq_no')->default(0)->comment('会话内消息序号自增水位');
            $table->timestamp('closed_at')->nullable()->comment('会话关闭时间');
            $table->timestamp('reopened_at')->nullable()->comment('会话最近一次被重新打开的时间，参与空闲自动关闭判定');
            $table->json('summary_translations')->nullable()->comment('按目标语言缓存的会话摘要译文');

            $table->index(['status', 'inbox_status', 'last_message_at'], 'conversations_inbox_idx');
            $table->index(['status', 'waiting_for_visitor_reply', 'last_message_at'], 'conversations_waiting_visitor_idx');
            $table->index(['assigned_user_id', 'status', 'last_message_at'], 'conversations_assigned_idx');
            $table->index('contact_id');
            $table->index('channel_id');
            $table->index('reception_plan_version_id', 'idx_conversations_plan_version');
        });

        DB::statement(
            'CREATE UNIQUE INDEX conversations_one_open_per_contact_channel '.
            'ON conversations (channel_id, contact_id) '.
            "WHERE status = 'open' AND contact_id IS NOT NULL AND channel_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS conversations_one_open_per_contact_channel');
        Schema::dropIfExists('conversations');
    }
};
