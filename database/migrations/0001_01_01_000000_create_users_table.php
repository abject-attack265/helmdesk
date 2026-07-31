<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamps();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('avatar')->nullable();
            $table->string('locale', 20)->default('zh-CN');
            $table->string('timezone', 80)->nullable();
            $table->json('notification_preferences')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
