<?php

namespace App\Services\Storage;

use App\Enums\AttachmentPurpose;
use Illuminate\Support\Str;

/**
 * 生成附件对象 key 和安全扩展名。
 */
class AttachmentPathGenerator
{
    /**
     * @var array<string, string>
     */
    private const array MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'text/markdown' => 'md',
        'text/plain' => 'txt',
        'text/html' => 'html',
        'text/csv' => 'csv',
        'application/json' => 'json',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'video/mp4' => 'mp4',
    ];

    /**
     * @var list<string>
     */
    private const array ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'md', 'markdown', 'txt', 'html', 'htm', 'csv', 'json', 'zip',
        '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'wav', 'm4a',
        'mp4', 'mov',
    ];

    /**
     * 生成仅供直传和校验阶段使用的临时对象 key。
     */
    public function generatePending(string $attachmentId): string
    {
        return sprintf('attachments/pending/%s/%s', now()->format('Y/m/d'), $attachmentId);
    }

    /**
     * 生成附件对象在存储里的完整 key。
     */
    public function generate(
        string $attachmentId,
        AttachmentPurpose $purpose,
        string $originalName,
        string $mimeType,
    ): string {
        $date = now();
        $extension = $this->extension($originalName, $mimeType);
        $suffix = $extension ? '.'.$extension : '';

        return sprintf(
            'attachments/%s/%s/%s/%s/%s%s',
            $purpose->value,
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $attachmentId,
            $suffix,
        );
    }

    /**
     * 从原始文件名和 MIME 类型推断安全扩展名。
     */
    public function extension(string $originalName, string $mimeType): ?string
    {
        $extension = Str::lower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';

        if ($extension !== '' && strlen($extension) <= 10 && in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        return self::MIME_EXTENSIONS[$mimeType] ?? null;
    }
}
