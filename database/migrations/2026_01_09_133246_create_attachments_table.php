<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->comment('通用附件：知识库文档 / 渠道图标 / 头像等可附着对象的文件元数据');

            $table->ulid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('uploaded_by_user_id')->nullable()->comment('上传者用户；系统 / 访客上传可为空');
            $table->foreignUlid('storage_profile_id')->constrained('storage_profiles');
            $table->string('object_key')->comment('对象存储中的文件 key');
            $table->string('original_name')->comment('上传时的原始文件名');
            $table->string('mime_type')->comment('MIME 类型');
            $table->string('extension', 20)->nullable()->comment('文件扩展名');
            $table->unsignedBigInteger('byte_size')->comment('文件大小（字节）');
            $table->string('checksum_sha256', 64)->nullable()->comment('文件 SHA256 校验和');
            $table->string('etag')->nullable()->comment('对象存储返回的 ETag');
            $table->string('purpose', 40)->default('other')->comment('用途：avatar / channel_icon / conversation_image / conversation_file / knowledge_document / import / other');
            $table->string('status', 20)->default('pending')->comment('生命周期状态：pending / uploaded / attached / failed / expired / deleted');
            $table->string('session_token_hash', 64)->nullable()->comment('访客上传会话 token 的 sha256；绑定到业务对象时复核归属');
            $table->nullableUlidMorphs('attachable');
            $table->json('metadata')->nullable()->comment('扩展元数据（图片尺寸等）');
            $table->timestamp('uploaded_at')->nullable()->comment('上传完成时间');
            $table->timestamp('attached_at')->nullable()->comment('附着到业务对象的时间');
            $table->timestamp('expires_at')->nullable()->comment('过期时间，过期可被回收');
            $table->string('upload_object_key')->nullable()->comment('直传签名对应的临时对象 key');
            $table->timestamp('upload_expires_at')->nullable()->comment('直传签名失效时间，失效后最终清理临时对象');

            $table->index(['purpose', 'status']);
            $table->index(['storage_profile_id', 'status']);
            $table->index('expires_at');
            $table->index('upload_expires_at');
            $table->unique(['storage_profile_id', 'object_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
