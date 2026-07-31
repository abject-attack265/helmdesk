<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 创建收件箱线程投影表及其查询索引。
     */
    public function up(): void
    {
        Schema::create('conversation_threads', function (Blueprint $table) {
            $table->comment('收件箱线程：每个联系人和渠道组合的当前会话投影');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('contact_id');
            $table->ulid('channel_id');
            $table->ulid('current_conversation_id')->comment('线程当前代表会话');
            $table->string('status', 20)->comment('当前会话状态：open / closed');
            $table->string('inbox_status', 30)->comment('当前接待状态');
            $table->ulid('assigned_user_id')->nullable()->comment('当前负责同事');
            $table->boolean('is_important')->default(false)->comment('联系人是否为重点客户，收件箱排序用');
            $table->timestamp('last_activity_at')->comment('线程最近活动时间，收件箱排序用');

            $table->unique('current_conversation_id', 'conversation_threads_current_conversation_unique');
            $table->unique(
                ['contact_id', 'channel_id'],
                'conversation_threads_identity_unique',
            );
        });

        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_open_inbox_idx
            ON conversation_threads (
                inbox_status,
                is_important DESC,
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'open'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_open_assignee_idx
            ON conversation_threads (
                assigned_user_id,
                is_important DESC,
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'open'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_open_channel_idx
            ON conversation_threads (
                channel_id,
                is_important DESC,
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'open'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_closed_activity_idx
            ON conversation_threads (
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'closed'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_closed_important_activity_idx
            ON conversation_threads (
                is_important,
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'closed'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_closed_assignee_idx
            ON conversation_threads (
                assigned_user_id,
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'closed'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversation_threads_closed_channel_idx
            ON conversation_threads (
                channel_id,
                last_activity_at DESC,
                id DESC
            )
            WHERE status = 'closed'
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversations_thread_closed_latest_idx
            ON conversations (
                contact_id,
                channel_id,
                closed_at DESC,
                created_at DESC,
                id DESC
            )
            WHERE status = 'closed' AND contact_id IS NOT NULL AND channel_id IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX conversations_contact_created_idx
            ON conversations (contact_id, created_at, id)
            WHERE contact_id IS NOT NULL
            SQL);
    }

    /**
     * 删除收件箱线程投影表及会话辅助索引。
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS conversations_contact_created_idx');
        DB::statement('DROP INDEX IF EXISTS conversations_thread_closed_latest_idx');
        Schema::dropIfExists('conversation_threads');
    }
};
