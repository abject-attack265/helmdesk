<?php

declare(strict_types=1);

namespace App\Services\AiRuntime;

use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Providers\OpenAI\MessageMapper as OpenAIMessageMapper;

/**
 * 支持视频输入的 OpenAI 兼容消息映射器。
 *
 * 图片块沿用父类的 image_url 映射；视频输入是国产多模态模型（MiniMax、小米 MiMo 等）的共同扩展
 * （{type: video_url, video_url: {url, fps}}），不在标准 OpenAI 规范里，NeuronAI 原生 mapper 会把
 * VideoContent 直接丢弃，故在此补上视频块的映射。
 */
class OpenAiVideoMessageMapper extends OpenAIMessageMapper
{
    /** 视频抽帧频率（帧/秒）。 */
    private const float VIDEO_FPS = 1.0;

    /**
     * 在父类基础上补充视频内容块映射，其余块（文本/图片/文件）沿用父类。
     */
    protected function mapContentBlock(ContentBlockInterface $block): ?array
    {
        if ($block instanceof VideoContent) {
            return $this->mapVideoBlock($block);
        }

        return parent::mapContentBlock($block);
    }

    /**
     * 把视频内容块映射成 video_url 结构。
     *
     * @return array<string, mixed>
     */
    protected function mapVideoBlock(VideoContent $block): array
    {
        return [
            'type' => 'video_url',
            'video_url' => [
                'url' => match ($block->sourceType) {
                    SourceType::URL, SourceType::ID => $block->content,
                    SourceType::BASE64 => 'data:'.$block->mediaType.';base64,'.$block->content,
                },
                'fps' => self::VIDEO_FPS,
            ],
        ];
    }
}
