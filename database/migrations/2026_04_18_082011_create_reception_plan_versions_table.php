<?php

use App\Enums\ReceptionPlanVersionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_plan_versions', function (Blueprint $table) {
            $table->comment('接待方案版本：不可变快照，渠道运行时按版本解析配置');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('reception_plan_id')->comment('所属接待方案');

            $table->unsignedInteger('version_number')->comment('方案内递增版本号');
            $table->string('description', 500)->nullable()->comment('版本说明 / 变更备注');

            $table->json('snapshot_config')->comment('发布时的方案配置快照（原样保存）');
            $table->json('compiled_config')->comment('运行时编译后的配置（供渠道直接解析）');

            $table->string('status', 20)->default(ReceptionPlanVersionStatus::Published->value)->comment('版本状态：published / archived');

            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->ulid('published_by_user_id')->nullable()->comment('发布操作的用户');

            $table->unique(['reception_plan_id', 'version_number'], 'uniq_reception_plan_versions_plan_number');
            $table->index(['reception_plan_id', 'status'], 'idx_reception_plan_versions_plan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_plan_versions');
    }
};
