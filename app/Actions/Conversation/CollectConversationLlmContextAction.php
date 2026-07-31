<?php

namespace App\Actions\Conversation;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 收集适合提供给 LLM 的会话文本上下文。
 */
class CollectConversationLlmContextAction
{
    use AsAction;

    /** 单次生成任务允许读取的会话文本字符数。 */
    private const int MAX_CONTEXT_CHARACTERS = 150_000;

    /**
     * 按消息顺序返回未撤回的访客、AI 与人工文本，并在字符预算处截断。
     *
     * @return list<array{role: string, content: string}>
     */
    public function handle(Conversation $conversation): array
    {
        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->whereNull('recalled_at')
            ->orderBy('seq_no')
            ->get(['role', 'content', 'seq_no']);

        $remaining = self::MAX_CONTEXT_CHARACTERS;
        $collected = [];

        foreach ($messages as $message) {
            $content = trim((string) $message->content);
            if ($content === '') {
                continue;
            }

            $length = Str::length($content);
            if ($length > $remaining) {
                $content = Str::substr($content, 0, $remaining);
                $length = Str::length($content);
            }

            $collected[] = [
                'role' => $message->role->value,
                'content' => $content,
            ];

            $remaining -= $length;
            if ($remaining <= 0) {
                break;
            }
        }

        return $collected;
    }
}
