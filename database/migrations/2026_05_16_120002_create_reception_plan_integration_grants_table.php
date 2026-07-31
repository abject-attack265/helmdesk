<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_plan_integration_grants', function (Blueprint $table) {
            $table->comment('接待方案对某集成的工具授权：可选白名单收窄到该集成的部分已启用工具');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('reception_plan_id');
            $table->ulid('integration_id');
            $table->json('tool_whitelist')->nullable()->comment('工具名白名单，null=该集成全部已启用工具');

            $table->unique(['reception_plan_id', 'integration_id'], 'uniq_plan_integration_grant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reception_plan_integration_grants');
    }
};
