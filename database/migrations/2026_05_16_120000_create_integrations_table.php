<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->comment('集成配置：应用接入的外部能力来源（当前 provider 为 MCP，Streamable HTTP）');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->string('provider')->default('mcp')->comment('集成 provider 类型');
            $table->string('slug');
            $table->string('name');
            $table->string('transport')->default('streamable_http')->comment('传输协议，当前仅支持 streamable_http');
            $table->string('endpoint_url')->comment('MCP 服务端点 URL');
            $table->text('credentials')->nullable()->comment('加密存储的认证凭据 JSON（如 bearer token）');
            $table->json('headers')->nullable()->comment('用户自定义请求头键值对，随每次调用一起发送');
            $table->unsignedSmallInteger('timeout_seconds')->default(30)->comment('单次调用超时时间（秒）');
            $table->timestamp('last_synced_at')->nullable()->comment('最近一次同步工具列表的时间');
            $table->string('last_sync_status')->default('pending')->comment('最近一次同步结果：pending / syncing / success / failed');
            $table->text('last_sync_error')->nullable()->comment('最近一次同步失败的错误信息');
            $table->integer('sort_order')->default(0)->comment('列表展示排序，值越小越靠前');

            $table->unique('slug');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
