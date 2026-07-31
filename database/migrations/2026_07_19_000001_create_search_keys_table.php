<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_keys', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->string('model_id', 26);
            $table->unique(['model_type', 'model_id']);
            $table->index(['model_type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_keys');
    }
};
