<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->comment('接待渠道：访客接入入口，每个渠道绑定一套接待方案');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();

            $table->string('type')->comment('渠道类型：web / telegram / wechat_oa');
            $table->string('name')->comment('渠道名称');
            $table->text('description')->nullable()->comment('渠道说明');
            $table->string('code')->unique()->comment('渠道公开定位码，用于访客端或 webhook URL');
            $table->ulid('reception_plan_id')->nullable()->comment('绑定的接待方案；运行时跟随方案最新版解析');
            $table->json('settings')->nullable()->comment('渠道配置 JSON；敏感字段由 ChannelSettingsCast 加密');

            $table->string('first_embed_host', 255)->nullable()->comment('widget 首次嵌入的宿主域名（装机健康度观察）');
            $table->timestamp('first_embed_at')->nullable()->comment('widget 首次嵌入时间');
            $table->string('last_embed_host', 255)->nullable()->comment('widget 最近一次嵌入的宿主域名');
            $table->timestamp('last_embed_at')->nullable()->comment('widget 最近一次嵌入时间');

            $table->index('type');
            $table->index('reception_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
