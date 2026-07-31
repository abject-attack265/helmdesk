<?php

namespace App\Services\Ai;

use App\Data\Conversation\ConversationAiContextMessageData;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 将客户会话消息整理为带附件访问地址和字符预算的通用 AI 上下文。
 */
class ConversationAiContextBuilder
{
    /** 当前会话历史正文的默认字符预算。 */
    public const int DEFAULT_MAX_CHARACTERS = 50_000;

    /**
     * 从最新消息向前保留预算内的当前会话上下文。
     *
     * @param  Collection<int, ConversationMessage>  $history
     * @return list<ConversationAiContextMessageData>
     */
    public function currentMessages(
        Conversation $conversation,
        Collection $history,
        int $maxCharacters = self::DEFAULT_MAX_CHARACTERS,
    ): array {
        $remaining = $maxCharacters;
        $selected = [];
        $truncated = false;

        foreach ($history->reverse()->values() as $index => $message) {
            $entry = $this->message($message);
            $textLength = mb_strlen($entry->content);
            $reachesLimit = $textLength >= $remaining;
            $hasUnvisitedOlderMessages = $index < $history->count() - 1;
            $truncateHere = $textLength > $remaining || ($reachesLimit && $hasUnvisitedOlderMessages);
            $entry->content = $this->tailWithinBudget($entry->content, $remaining, $truncateHere);
            $selected[] = $entry;
            $remaining -= min($textLength, $remaining);

            if ($remaining === 0) {
                $truncated = $truncateHere;
                break;
            }
        }

        if ($truncated) {
            Log::info('AI 当前会话上下文达到字符上限。', [
                'conversation_id' => (string) $conversation->id,
                'available_message_count' => $history->count(),
                'included_message_count' => count($selected),
                'character_limit' => $maxCharacters,
            ]);
        }

        return array_reverse($selected);
    }

    /**
     * 将一条持久化消息转换为通用 AI 上下文消息。
     */
    public function message(ConversationMessage $message): ConversationAiContextMessageData
    {
        $parts = [];

        $content = trim($message->content ?? '');
        if ($content !== '') {
            $parts[] = $content;
        }

        foreach ($message->attachments as $attachment) {
            $parts[] = $this->attachmentPlaceholder($attachment);
        }

        return new ConversationAiContextMessageData(
            role: $message->role,
            content: trim(implode(' ', $parts)),
        );
    }

    /**
     * 返回客户会话消息的稳定角色标签。
     */
    public function roleLabel(MessageRole $role): string
    {
        return match ($role) {
            MessageRole::Visitor => '访客',
            MessageRole::Teammate => '人工客服',
            MessageRole::Ai => 'AI客服',
            MessageRole::Tool => throw new \LogicException('工具消息不应进入会话 AI 上下文。'),
        };
    }

    /**
     * 保留预算内的文本尾部，并在需要时标识较早内容已截断。
     */
    public function tailWithinBudget(string $text, int $budget, bool $markTruncated = false): string
    {
        if (mb_strlen($text) <= $budget && ! $markTruncated) {
            return $text;
        }

        $marker = '[较早内容已截断] ';
        $markerLength = mb_strlen($marker);
        if ($budget <= $markerLength) {
            return mb_substr($text, -$budget);
        }

        return $marker.mb_substr($text, -($budget - $markerLength));
    }

    /**
     * 将附件转换为包含类型、文件名和访问地址的文字占位。
     */
    private function attachmentPlaceholder(Attachment $attachment): string
    {
        $mime = (string) $attachment->mime_type;
        $name = (string) $attachment->original_name;
        $url = $attachment->full_url;

        if (Str::startsWith($mime, 'image/')) {
            return "[图片：{$name}；链接：{$url}]";
        }

        if (Str::startsWith($mime, 'video/')) {
            return "[视频：{$name}；链接：{$url}]";
        }

        return "[文件：{$name}；链接：{$url}]";
    }
}
