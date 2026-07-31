<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_ratings', function (Blueprint $table) {
            $table->comment('访客对会话的满意度评价（CSAT）；每会话最多一条，可覆盖更新');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('conversation_id')->unique()->comment('被评价会话，指向 conversations.id；每会话唯一');
            $table->ulid('contact_id')->nullable()->comment('评价访客，指向 contacts.id');
            $table->string('score')->comment('评分：positive / negative（ConversationRatingScore）');
            $table->text('comment')->nullable()->comment('选填文字反馈');
            $table->string('channel_type')->comment('评价时渠道类型快照，便于分渠道统计');
            $table->string('handled_by')->comment('处理归属快照：ai / human，用于拆分 AI 与人工满意度');
            $table->ulid('assigned_user_id')->nullable()->comment('处理坐席快照（人工时），用于每坐席 CSAT');
            $table->timestamp('rated_at')->comment('评价提交时间');

            $table->index('rated_at', 'conversation_ratings_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_ratings');
    }
};
