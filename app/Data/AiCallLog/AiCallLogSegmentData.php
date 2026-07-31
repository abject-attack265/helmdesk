<?php

namespace App\Data\AiCallLog;

use Spatie\LaravelData\Data;

/**
 * AI 调用日志详情中的模型正文或工具调用分段。
 */
class AiCallLogSegmentData extends Data
{
    public function __construct(
        /** 分段类型：text / tool_call */
        public string $type,
        /** 正文文本（type=text 时有值） */
        public ?string $content,
        /** 工具名称（type=tool_call 时有值） */
        public ?string $name,
        /** 工具入参；对象或数组，原样透传给前端 JSON 展示 */
        public mixed $inputs,
        /** 工具执行返回；正文分段为 null */
        public ?string $result,
        /** 标识 respond 工具产生的访客消息 */
        public bool $sent_to_visitor,
    ) {}

    /**
     * 从调用日志分段构造详情数据。
     *
     * @param  array<string, mixed>  $segment
     */
    public static function fromStored(array $segment): self
    {
        return match ($segment['type']) {
            'text' => new self(
                type: 'text',
                content: $segment['content'],
                name: null,
                inputs: null,
                result: null,
                sent_to_visitor: false,
            ),
            'tool_call' => new self(
                type: 'tool_call',
                content: null,
                name: $segment['name'],
                inputs: $segment['inputs'],
                result: $segment['result'],
                sent_to_visitor: $segment['name'] === 'respond',
            ),
        };
    }
}
