<?php

use App\Enums\UserOnlineStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->comment('后台成员资料；一个用户最多一条记录');

            $table->timestamps();
            $table->ulid('user_id')->primary();
            $table->integer('online_status')->default(UserOnlineStatus::Online->value);
            $table->string('nickname', 50)->nullable();
            $table->timestamp('last_active_at')->nullable()->comment('最后活跃时间');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
