<?php

namespace App\Services\Storage;

use finfo;

/**
 * 从附件真实字节中识别 MIME 类型。
 */
class AttachmentMimeDetector
{
    /** 内容嗅探读取的字节数，覆盖常见类型的 magic bytes。 */
    private const int SNIFF_BYTES = 4096;

    /**
     * 从完整文件内容识别 MIME 类型，无法识别时返回 null。
     */
    public function detectContents(string $contents): ?string
    {
        return $this->detectBuffer(substr($contents, 0, self::SNIFF_BYTES));
    }

    /**
     * 从可读流头部识别 MIME 类型，无法读取或识别时返回 null。
     *
     * @param  resource  $stream
     */
    public function detectStream(mixed $stream): ?string
    {
        if (! is_resource($stream)) {
            return null;
        }

        $head = fread($stream, self::SNIFF_BYTES);

        return is_string($head) ? $this->detectBuffer($head) : null;
    }

    /**
     * 从非空字节片段识别并规范化 MIME 类型。
     */
    private function detectBuffer(string $buffer): ?string
    {
        if ($buffer === '') {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($buffer);

        return is_string($mime) && $mime !== ''
            ? MimeType::normalize($mime)
            : null;
    }
}
