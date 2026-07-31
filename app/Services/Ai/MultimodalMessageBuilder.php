<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;

/**
 * 按模型输入能力将会话附件构建成媒体内容块或文字占位。
 */
class MultimodalMessageBuilder
{
    /** 单张图片允许进入模型请求的体积上限。 */
    private const int MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** 单段视频允许进入模型请求的体积上限。 */
    private const int MAX_VIDEO_BYTES = 50 * 1024 * 1024;

    /**
     * 将附件按输入顺序构建成模型内容块。
     *
     * @param  iterable<Attachment>  $attachments
     * @return list<ContentBlockInterface>
     */
    public function attachmentBlocks(
        iterable $attachments,
        bool $supportsImageInput,
        bool $supportsVideoInput,
        string $aiModelId,
    ): array {
        $blocks = [];
        foreach ($attachments as $attachment) {
            $blocks[] = $this->mediaBlock(
                $attachment,
                $supportsImageInput,
                $supportsVideoInput,
                $aiModelId,
            );
        }

        return $blocks;
    }

    /**
     * 将单个附件映射成公开 URL 媒体块或文字占位。
     */
    private function mediaBlock(
        Attachment $attachment,
        bool $supportsImageInput,
        bool $supportsVideoInput,
        string $aiModelId,
    ): ContentBlockInterface {
        $mime = (string) $attachment->mime_type;

        if (Str::startsWith($mime, 'image/')) {
            if (! $supportsImageInput) {
                return $this->textFallback($attachment, $aiModelId, 'image_input_not_supported');
            }

            if ($attachment->byte_size > self::MAX_IMAGE_BYTES) {
                return $this->textFallback($attachment, $aiModelId, 'image_size_limit_exceeded', true);
            }

            return new ImageContent($attachment->full_url, SourceType::URL, $mime);
        }

        if (Str::startsWith($mime, 'video/')) {
            if (! $supportsVideoInput) {
                return $this->textFallback($attachment, $aiModelId, 'video_input_not_supported');
            }

            if ($attachment->byte_size > self::MAX_VIDEO_BYTES) {
                return $this->textFallback($attachment, $aiModelId, 'video_size_limit_exceeded', true);
            }

            return new VideoContent($attachment->full_url, SourceType::URL, $mime);
        }

        return $this->textFallback($attachment, $aiModelId, 'attachment_type_not_supported');
    }

    /**
     * 记录降级原因并返回附件文字占位。
     */
    private function textFallback(
        Attachment $attachment,
        string $aiModelId,
        string $reason,
        bool $warning = false,
    ): TextContent {
        $context = [
            'ai_model_id' => $aiModelId,
            'attachment_id' => (string) $attachment->id,
            'mime_type' => (string) $attachment->mime_type,
            'byte_size' => $attachment->byte_size,
            'fallback_reason' => $reason,
        ];

        if ($warning) {
            Log::warning('[ai] 媒体附件超过输入体积限制，使用文字占位', $context);
        } else {
            Log::info('[ai] 附件使用文字占位进入模型请求', $context);
        }

        return new TextContent($this->placeholderFor($attachment));
    }

    /**
     * 生成附件的模型可读文字描述。
     */
    private function placeholderFor(Attachment $attachment): string
    {
        $mime = (string) $attachment->mime_type;
        $name = (string) $attachment->original_name;

        if (Str::startsWith($mime, 'image/')) {
            return "[访客发送了一张图片：{$name}]";
        }

        if (Str::startsWith($mime, 'video/')) {
            return "[访客发送了一段视频：{$name}]";
        }

        return "[访客发送了一个文件：{$name}]";
    }
}
