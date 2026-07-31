<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_identities', function (Blueprint $table) {
            $table->comment('联系人身份标识：跨渠道的外部账号或会话身份映射');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('contact_id')->comment('所属联系人 contacts.id');
            $table->string('type')->comment('身份类型：session 会话 / email / phone / external_id 外部 ID');
            $table->string('namespace')->default('')->comment('命名空间：用于区分同类型不同渠道来源（如不同 IM 平台）');
            $table->string('value')->comment('身份原始值：用于唯一匹配（如 token、邮箱、外部用户 ID）');
            $table->string('display_value')->nullable()->comment('展示值：面向坐席的可读身份');

            $table->index('contact_id');
        });

        DB::statement('CREATE UNIQUE INDEX contact_identities_unique_active ON contact_identities (type, namespace, value) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contact_identities_unique_active');
        Schema::dropIfExists('contact_identities');
    }
};
