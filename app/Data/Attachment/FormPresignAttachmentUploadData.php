<?php

namespace App\Data\Attachment;

use App\Enums\AttachmentPurpose;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 申请附件直传签名的表单数据，来自头像、Logo、聊天附件等前端上传入口。
 * 浏览器先用这些声明信息换取 presigned PUT，再直传到对象存储。
 */
class FormPresignAttachmentUploadData extends Data
{
    /**
     * 承载申请直传时声明的文件信息与业务上下文。
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public AttachmentPurpose $purpose,
        public string $file_name,
        public string $mime_type,
        public int $byte_size,
        public array $context = [],
    ) {}

    /**
     * 返回直传签名申请的验证规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::enum(AttachmentPurpose::class)],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:120'],
            'byte_size' => ['required', 'integer', 'min:1'],
            'context' => ['sometimes', 'array'],
        ];
    }
}
