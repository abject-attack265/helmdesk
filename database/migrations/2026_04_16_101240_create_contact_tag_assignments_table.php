<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tag_assignments', function (Blueprint $table) {
            $table->comment('联系人标签分配（含 AI / 人工来源与抑制边界）');

            $table->ulid('tag_id')->comment('标签 tags.id');
            $table->ulid('contact_id')->comment('联系人 contacts.id');
            $table->ulid('assigned_by_user_id')->nullable()->comment('打标操作人 user_id：系统/AI 来源时为空');
            $table->string('source')->default('manual')->comment('打标来源：manual 人工 / system 系统 / ai / import 导入 / channel 渠道');
            $table->timestamp('created_at')->nullable();

            $table->unique(['tag_id', 'contact_id']);
            $table->index(['contact_id', 'tag_id'], 'cta_contact_tag_idx');
            $table->index(['tag_id', 'created_at'], 'cta_tag_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag_assignments');
    }
};
