<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_groups', function (Blueprint $table) {
            $table->comment('标签组：标签的维度容器（会话维 / 联系人维）');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('name')->comment('标签组名');
            $table->string('normalized_name')->comment('标准化组名');
            $table->string('scope')->comment('适用维度：conversation / contact');
            $table->unsignedInteger('sort_order')->default(0)->comment('组排序权重');
            $table->ulid('created_by_user_id')->nullable();
            $table->ulid('updated_by_user_id')->nullable();

            $table->index('scope');
        });

        DB::statement(
            'CREATE UNIQUE INDEX tag_groups_normalized_name_active_unique ON tag_groups (normalized_name) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_groups');
    }
};
