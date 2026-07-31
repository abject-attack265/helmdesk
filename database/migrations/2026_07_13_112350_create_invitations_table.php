<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->comment('成员邀请：邮箱 + 一次性令牌，接受后建账号');

            $table->ulid('id')->primary();
            $table->string('email');
            $table->string('nickname', 50)->nullable()->comment('接受后成员的对外昵称');
            $table->json('permissions');
            $table->string('token', 64)->unique()->comment('邀请令牌的 sha256 哈希；明文只进邮件链接');
            $table->ulid('invited_by')->comment('发起邀请的用户');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
