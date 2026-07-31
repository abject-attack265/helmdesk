<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_rag')->create('knowledge_vector_tables', function (Blueprint $table): void {
            $table->unsignedInteger('dimension')->primary();
            $table->string('table_name');
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        $connection = DB::connection('sqlite_rag');

        foreach ($connection->table('knowledge_vector_tables')->pluck('table_name') as $tableName) {
            $connection->statement('DROP TABLE '.$tableName);
        }

        Schema::connection('sqlite_rag')->dropIfExists('knowledge_vector_tables');
    }
};
