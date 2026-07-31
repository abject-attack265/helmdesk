<?php

namespace App\Data\AiCallLog;

use Spatie\LaravelData\Data;

/**
 * AI 调用日志详情时间线中的一条用户或模型消息。
 */
class AiCallLogMessageData extends Data
{
    public function __construct(
        /** 角色：user / assistant */
        public string $role,
        /** 归属接待轮次 ID；非接待调用为 null */
        public ?string $turn_id,
        /** 纯文本内容（user=输入原文；assistant=各 text 分段拼接） */
        public string $text,
        /** 按事件顺序的分段（assistant 专用；user 为空数组） */
        /** @var AiCallLogSegmentData[] */
        public array $segments,
        /** 本条消息附带的图片 */
        /** @var AiCallLogImageData[] */
        public array $images,
        /** 本条 assistant 回复所用模型名；user 为 null */
        public ?string $model_name,
        /** 本条 assistant 回复的输入 token；user 为 null */
        public ?int $input_tokens,
        /** 本条 assistant 回复的输出 token；user 为 null */
        public ?int $output_tokens,
        /** 标识模型调用失败 */
        public bool $is_error,
        /** 失败原因；成功为 null */
        public ?string $error_message,
        public string $created_at,
    ) {}
}
