<?php

namespace App\Actions\AiChat;

use App\Enums\AiAssistantMessageRole;
use App\Enums\AiAssistantMessageSegmentType;
use App\Enums\AiAssistantMessageStatus;
use App\Models\AiAssistantMessage;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 幂等收口一条待生成的 AI 助手回答。
 */
class FinalizeAiAssistantMessageAction
{
    use AsAction;

    /**
     * 只更新 pending 助手消息，避免队列重试覆盖已完成结果。
     *
     * @param  list<array<string, mixed>>  $segments
     */
    public function handle(
        string $messageId,
        AiAssistantMessageStatus $status,
        array $segments = [],
    ): void {
        if ($status === AiAssistantMessageStatus::Pending) {
            throw new InvalidArgumentException('AI 助手消息只能收口为 completed 或 failed。');
        }

        $updated = AiAssistantMessage::query()
            ->whereKey($messageId)
            ->where('role', AiAssistantMessageRole::Assistant)
            ->where('status', AiAssistantMessageStatus::Pending)
            ->update([
                'content' => $this->textContent($segments),
                'segments' => $segments !== [] ? $segments : null,
                'status' => $status,
            ]);

        if ($updated === 1) {
            return;
        }

        $message = AiAssistantMessage::query()->findOrFail($messageId);
        if ($message->role !== AiAssistantMessageRole::Assistant) {
            throw new LogicException('只能收口 AI 助手回答消息。');
        }
    }

    /**
     * 按片段顺序拼接供检索和模型上下文使用的回答正文。
     *
     * @param  list<array<string, mixed>>  $segments
     */
    private function textContent(array $segments): ?string
    {
        $content = '';

        foreach ($segments as $segment) {
            $type = AiAssistantMessageSegmentType::from($segment['type']);

            if ($type === AiAssistantMessageSegmentType::Text) {
                if (! isset($segment['content']) || ! is_string($segment['content']) || $segment['content'] === '') {
                    throw new InvalidArgumentException('AI 助手文本片段必须包含正文。');
                }

                $content .= $segment['content'];

                continue;
            }

            if (! isset($segment['tool']) || ! is_string($segment['tool']) || trim($segment['tool']) === '') {
                throw new InvalidArgumentException('AI 助手工具片段必须包含工具名称。');
            }

            if ($type === AiAssistantMessageSegmentType::ToolCall && ! is_string($segment['args'] ?? null)) {
                throw new InvalidArgumentException('AI 助手工具调用片段必须包含参数。');
            }

            if ($type === AiAssistantMessageSegmentType::ToolResult && ! is_string($segment['content'] ?? null)) {
                throw new InvalidArgumentException('AI 助手工具结果片段必须包含结果。');
            }
        }

        return $content !== '' ? $content : null;
    }
}
