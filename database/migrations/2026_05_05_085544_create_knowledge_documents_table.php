<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->comment('知识库文档：原始文件 + 解析后正文');

            $table->ulid('id')->primary();
            $table->timestamps();

            $table->ulid('knowledge_base_id')->comment('所属知识库');
            $table->ulid('group_id')->comment('所属文档分组');
            $table->ulid('uploaded_by_user_id')->nullable()->comment('上传者用户');

            $table->string('original_filename')->comment('原始文件名');
            $table->string('mime_type', 191)->comment('文件 MIME 类型');
            $table->unsignedBigInteger('byte_size')->comment('文件字节大小');
            $table->string('extension', 16)->nullable()->comment('文件扩展名');
            $table->string('checksum_sha256', 64)->nullable()->comment('文件内容 SHA256，用于去重/校验');
            $table->string('source_type', 32)->default('upload')->comment('来源类型：upload 上传 / manual 手动录入');
            $table->string('status', 32)->default('pending')->comment('综合状态：pending/parsing/parsed/indexing/indexed/failed，由各阶段派生');
            $table->text('error_message')->nullable()->comment('综合状态对应的错误信息');
            $table->longText('content')->nullable()->comment('原始文本内容');
            $table->string('parse_status', 32)->default('pending')->comment('解析状态：pending/processing/succeeded/failed/skipped');
            $table->text('parse_error')->nullable()->comment('解析失败原因');
            $table->timestamp('parsed_at')->nullable()->comment('解析完成时间');
            $table->string('parsed_content_format', 16)->nullable()->comment('解析后正文格式，如 markdown');
            $table->longText('parsed_content')->nullable()->comment('解析后正文，供切分与索引使用');
            $table->json('parse_metadata')->nullable()->comment('解析元数据，如页数、分块信息');

            $table->string('vector_status', 32)->default('idle')->comment('向量索引状态：idle/pending/processing/succeeded/failed');
            $table->text('vector_error')->nullable()->comment('向量索引失败原因');
            $table->timestamp('vector_indexed_at')->nullable()->comment('向量索引完成时间');

            $table->string('raptor_status', 32)->default('idle')->comment('RAPTOR 摘要索引状态：idle/pending/processing/succeeded/failed');
            $table->text('raptor_error')->nullable()->comment('RAPTOR 索引失败原因');
            $table->timestamp('raptor_indexed_at')->nullable()->comment('RAPTOR 索引完成时间');

            $table->index(['knowledge_base_id', 'group_id'], 'idx_kb_doc_kb_group');
            $table->index('created_at', 'idx_kb_doc_created_at');
            $table->index(['knowledge_base_id', 'parse_status'], 'idx_kb_doc_kb_parse_status');
            $table->index(['knowledge_base_id', 'vector_status'], 'idx_kb_doc_kb_vector_status');
            $table->index(['knowledge_base_id', 'raptor_status'], 'idx_kb_doc_kb_raptor_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
