<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_timeline_entries', function (Blueprint $table) {
            $table->comment('会话时间线条目：收件箱联系人时间轴');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->ulid('contact_id')->nullable()->comment('关联联系人；匿名访客可为空');
            $table->ulid('conversation_id')->comment('所属会话');
            $table->string('entry_type', 20)->comment('条目类型：message / event');
            $table->ulid('entry_id')->comment('指向的消息或事件 ID，与 entry_type 配合定位源记录');
            $table->timestamp('occurred_at')->comment('条目发生时间，时间轴排序用');

            $table->unique(['entry_type', 'entry_id'], 'conversation_timeline_entry_unique');
            $table->index(['contact_id', 'occurred_at', 'id'], 'conversation_timeline_contact_idx');
            $table->index(['conversation_id', 'occurred_at', 'id'], 'conversation_timeline_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_timeline_entries');
    }
};
