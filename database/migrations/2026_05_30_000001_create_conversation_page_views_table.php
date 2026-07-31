<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_page_views', function (Blueprint $table) {
            $table->comment('访客浏览轨迹：一次会话内的多条页面访问记录（时间序列）');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('conversation_id')->comment('所属会话');
            $table->ulid('contact_id')->nullable()->comment('访客联系人');
            $table->text('url')->comment('访问页面 URL');
            $table->string('title')->nullable()->comment('页面标题');
            $table->text('referrer')->nullable()->comment('来源页 referrer');
            $table->timestamp('viewed_at')->comment('页面访问时间');

            $table->index(['conversation_id', 'viewed_at'], 'page_views_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_page_views');
    }
};
