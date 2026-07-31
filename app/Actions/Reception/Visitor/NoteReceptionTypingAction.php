<?php

namespace App\Actions\Reception\Visitor;

use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\IdentityType;
use App\Models\Channel;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Reception\VisitorRequestContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 访客「正在输入」信号入口（POST /api/chat/{code}/typing）。
 *
 * 仅作用于已存在的 AI 接待会话，告知 debounce 推迟本会话的 flush，
 * 让访客一句话拆成几段连发时 AI 等其打完再回。不落库、不创建会话、不暴露 conversation_id，
 * 无会话映射或会话已结束时静默返回 204。
 */
class NoteReceptionTypingAction
{
    use AsAction;

    public function __construct(
        private readonly ReceptionPipelineDispatcher $pipeline,
    ) {}

    /**
     * 处理 typing 信号。
     */
    public function asController(Request $request, string $code): Response
    {
        $ctx = VisitorRequestContext::fromRequest($request, $code);

        if ($ctx->sessionToken !== null) {
            $conversationId = $this->resolveActiveConversationId($code, $ctx->sessionToken);
            if ($conversationId !== null) {
                $this->pipeline->noteTyping($conversationId);
            }
        }

        return response()->noContent();
    }

    /**
     * 按渠道 code + 会话 token 解析当前 AI 接待中的会话 ID；无匹配返回 null。
     */
    private function resolveActiveConversationId(string $code, string $sessionToken): ?string
    {
        $channel = Channel::query()->where('code', $code)->first();
        if ($channel === null) {
            return null;
        }

        $contactId = ContactIdentity::query()

            ->where('type', IdentityType::Session)
            ->where('namespace', '')
            ->where('value', $sessionToken)
            ->value('contact_id');
        if ($contactId === null) {
            return null;
        }

        $conversationId = Conversation::query()
            ->where('channel_id', $channel->id)
            ->where('contact_id', $contactId)
            ->where('status', ConversationStatus::Open)
            ->where('inbox_status', ConversationInboxStatus::AiHandling)
            ->latest('id')
            ->value('id');

        return $conversationId !== null ? (string) $conversationId : null;
    }
}
