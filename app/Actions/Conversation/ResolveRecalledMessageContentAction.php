<?php

namespace App\Actions\Conversation;

use App\Enums\MessageRole;
use App\Models\User;
use App\Services\Conversation\ConversationMessagePayloadDecoder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按查看者身份决定是否下发已撤回消息的原文。
 *
 * 客服本人发送的消息和应用 AI 消息支持重新编辑，访客消息不下发原文。
 */
class ResolveRecalledMessageContentAction
{
    use AsAction;

    /**
     * $row 是 selectRaw 取出来的时间线 stdClass，需要至少包含 role / actor_user_id / content。
     */
    public function handle(object $row, bool $isRecalled, ?User $viewer): ?string
    {
        if (! $isRecalled || $viewer === null || $row->content === null) {
            return null;
        }

        $role = (string) $row->role;
        $actor = $row->actor_user_id !== null ? (string) $row->actor_user_id : null;
        $viewerText = $this->viewerText($row->payload ?? null, $viewer->locale);
        if ($role === MessageRole::Teammate->value && $actor !== null && $actor === (string) $viewer->id) {
            return $viewerText ?? (string) $row->content;
        }

        if ($role === MessageRole::Ai->value) {
            return $viewerText ?? (string) $row->content;
        }

        return null;
    }

    /**
     * 读取当前客服语言下的消息内容。
     */
    private function viewerText(mixed $payload, string $locale): ?string
    {
        $decoded = ConversationMessagePayloadDecoder::decode($payload);
        if ($decoded === null) {
            return null;
        }

        $text = $decoded['translations'][$locale]['text'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }
}
