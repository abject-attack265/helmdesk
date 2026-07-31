<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_tag_assignments', function (Blueprint $table) {
            $table->comment('会话标签分配（含 AI / 人工来源与抑制边界）');

            $table->ulid('conversation_id');
            $table->ulid('tag_id');
            $table->string('source')->default('ai')->comment('打标来源：ai / manual');
            $table->float('confidence')->nullable()->comment('AI 打标置信度');
            $table->text('reason')->nullable()->comment('AI 打标依据（引用的原句/摘要事实）');
            $table->ulid('assigned_by_user_id')->nullable()->comment('人工打标的操作者');
            $table->unsignedBigInteger('based_on_seq_no')->nullable()->comment('打标时会话进展到的消息序号');
            $table->timestamp('removed_at')->nullable()->comment('人工抑制墓碑：被人工移除后置位，AI 重算不再复打');
            $table->ulid('removed_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'tag_id']);
            $table->index(['tag_id', 'created_at'], 'cnvta_tag_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_tag_assignments');
    }
};
