<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->comment('标签：归属于标签组，按组继承维度');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('tag_group_id')->comment('所属标签组 ID（标签必属于一个组，维度经由组继承）');
            $table->string('name')->comment('标签名');
            $table->string('normalized_name')->comment('标准化标签名');
            $table->string('color')->nullable()->comment('标签颜色');
            $table->string('description')->nullable()->comment('标签描述');
            $table->string('source')->default('manual')->comment('标签来源');
            $table->boolean('is_locked')->default(false)->comment('是否锁定');
            $table->ulid('created_by_user_id')->nullable();
            $table->ulid('updated_by_user_id')->nullable();

            $table->index('tag_group_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX tags_group_normalized_name_active_unique ON tags (tag_group_id, normalized_name) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
