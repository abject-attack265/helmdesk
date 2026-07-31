<?php

namespace App\Actions\Attachment;

use App\Enums\AttachmentPurpose;
use App\Services\Storage\MimeType;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 校验附件上传用途对应的大小和类型规则。
 */
class ValidateAttachmentUploadAction
{
    use AsAction;

    /**
     * 渲染为文档时可执行脚本或可作为可执行体的危险类型，无论用途一律拒绝。
     *
     * @var list<string>
     */
    private const array BLOCKED_MIME_TYPES = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'application/javascript',
        'text/javascript',
        'application/ecmascript',
        'text/ecmascript',
        'application/wasm',
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-executable',
        'application/vnd.microsoft.portable-executable',
        'application/x-mach-binary',
        'application/x-sh',
        'application/x-csh',
        'application/x-php',
        'application/x-httpd-php',
    ];

    /**
     * 会话文件允许的 MIME 白名单：覆盖常见图片、文档、表格、演示、归档与文本类型。
     * 客户可发的常见附件之外的类型一律拒绝，避免空白名单放行任意内容。
     *
     * @var list<string>
     */
    private const array CONVERSATION_FILE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/json',
        'application/zip',
        'application/x-zip-compressed',
        'application/gzip',
        'application/x-7z-compressed',
        'application/x-rar-compressed',
    ];

    /**
     * 知识库文档直传允许的 MIME 白名单，与当前文档解析/表单上传支持范围保持收敛。
     *
     * @var list<string>
     */
    private const array KNOWLEDGE_DOCUMENT_MIME_TYPES = [
        'text/plain',
        'text/markdown',
        'text/x-markdown',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * 校验文件类型和大小，并返回当前用途的上传规则。
     *
     * @return array{max_size: int, mime_types: list<string>}
     *
     * @throws ValidationException
     */
    public function handle(AttachmentPurpose $purpose, string $mimeType, int $byteSize): array
    {
        $rule = $this->ruleForPurpose($purpose);
        $normalizedMime = MimeType::normalize($mimeType);

        if (in_array($normalizedMime, self::BLOCKED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'mime_type' => __('attachments.errors.blocked_mime'),
            ]);
        }

        if ($byteSize <= 0 || $byteSize > $rule['max_size']) {
            throw ValidationException::withMessages([
                'byte_size' => __('validation.max.file', ['max' => (int) ceil($rule['max_size'] / 1024)]),
            ]);
        }

        if ($rule['mime_types'] !== [] && ! in_array($normalizedMime, $rule['mime_types'], true)) {
            throw ValidationException::withMessages([
                'mime_type' => __('validation.mimes', ['values' => implode(', ', $rule['mime_types'])]),
            ]);
        }

        return $rule;
    }

    /**
     * 返回指定用途对应的大小和类型规则。
     *
     * @return array{max_size: int, mime_types: list<string>}
     */
    private function ruleForPurpose(AttachmentPurpose $purpose): array
    {
        return match ($purpose) {
            AttachmentPurpose::Avatar, AttachmentPurpose::ChannelIcon => [
                'max_size' => 2 * 1024 * 1024,
                'mime_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                ],
            ],
            AttachmentPurpose::ConversationImage => [
                'max_size' => 10 * 1024 * 1024,
                'mime_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                ],
            ],
            AttachmentPurpose::ConversationFile => [
                'max_size' => 50 * 1024 * 1024,
                'mime_types' => self::CONVERSATION_FILE_MIME_TYPES,
            ],
            AttachmentPurpose::Import => [
                'max_size' => 100 * 1024 * 1024,
                'mime_types' => [
                    'text/csv',
                    'application/json',
                    'text/plain',
                    'application/pdf',
                ],
            ],
            AttachmentPurpose::Other => [
                'max_size' => 10 * 1024 * 1024,
                'mime_types' => [
                    'application/pdf',
                    'text/plain',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
            ],
            AttachmentPurpose::KnowledgeDocument => [
                'max_size' => 20 * 1024 * 1024,
                'mime_types' => self::KNOWLEDGE_DOCUMENT_MIME_TYPES,
            ],
        };
    }
}
