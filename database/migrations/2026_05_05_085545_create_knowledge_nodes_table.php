<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlite_rag')->create('knowledge_nodes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('knowledge_base_id');
            $table->ulid('document_id')->nullable();
            $table->ulid('qa_entry_id')->nullable();
            $table->ulid('qa_question_id')->nullable();
            $table->ulid('parent_id')->nullable();

            $table->string('strategy', 16);
            $table->unsignedTinyInteger('level')->default(0);
            $table->string('kind', 16);

            $table->longText('content');
            $table->string('content_format', 16)->default('markdown');
            $table->string('heading_path')->nullable();
            $table->unsignedInteger('byte_start')->nullable();
            $table->unsignedInteger('byte_end')->nullable();
            $table->unsignedInteger('token_count')->nullable();

            $table->ulid('embedding_model_id')->nullable();
            $table->unsignedSmallInteger('embedding_dim');
            $table->json('metadata')->nullable();

            $table->index(['knowledge_base_id', 'strategy', 'level'], 'idx_kn_node_kb_strategy_level');
            $table->index(['document_id', 'strategy'], 'idx_kn_node_doc_strategy');
            $table->index(['qa_entry_id', 'strategy'], 'idx_kn_node_qa_strategy');
            $table->index('parent_id', 'idx_kn_node_parent');
            $table->index(['knowledge_base_id', 'embedding_dim'], 'idx_kn_node_kb_dim');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlite_rag')->dropIfExists('knowledge_nodes');
    }
};
