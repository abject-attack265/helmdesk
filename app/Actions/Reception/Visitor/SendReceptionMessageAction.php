<?php

namespace App\Actions\Reception\Visitor;

use App\Actions\Reception\AppendVisitorMessageAction;
use App\Data\Reception\ReceptionMessageData;
use App\Enums\ConversationEntryMode;
use App\Enums\ConversationInboxStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Reception\ReceptionActivityRegistry;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Reception\VisitorRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/** 接收访客消息请求，保存消息并按接待状态调度 AI 回复。 */
class SendReceptionMessageAction
{
    use AsAction;

    /** 人工接待状态不触发 AI turn。 */
    private const array HUMAN_RECEPTION_STATUSES = [
        ConversationInboxStatus::TeammatePending,
        ConversationInboxStatus::TeammateHandling,
    ];

    /**
     * 注入消息落库、接待管线与活动租约服务。
     */
    public function __construct(
        private readonly AppendVisitorMessageAction $appendVisitorMessage,
        private readonly ReceptionPipelineDispatcher $pipeline,
        private readonly ReceptionActivityRegistry $activityRegistry,
    ) {}

    /**
     * 处理访客发送消息请求。
     */
    public function asController(Request $request, string $code): JsonResponse
    {
        $ctx = VisitorRequestContext::fromRequest($request, $code);
        $body = $this->readMessageContent($request);

        $state = $this->appendVisitorMessage->handle(
            channelCode: $code,
            sessionToken: $ctx->sessionToken,
            content: $body['content'],
            entryMode: ConversationEntryMode::from($ctx->entryMode),
            visitorEnvironment: $ctx->visitorEnvironment,
            attachmentIds: $body['attachment_ids'],
            userToken: $ctx->userToken,
            queryParams: $ctx->queryParams,
            visitorClient: $ctx->visitorClient,
            clientMsgId: $body['client_msg_id'],
            quotedMessageId: $body['quoted_message_id'],
        );

        if ($state->conversation_id === null) {
            throw new LogicException('访客消息写入后缺少会话 ID。');
        }

        $conversation = Conversation::query()->findOrFail($state->conversation_id);

        // 人工接待会话保留消息，但不调度 AI 回复。
        $mediaMessageIds = $this->resolveMediaMessageIds($body['attachment_ids']);
        if (($body['content'] !== '' || $mediaMessageIds !== [])
            && ! in_array($conversation->inbox_status, self::HUMAN_RECEPTION_STATUSES, true)) {
            $this->pipeline->enqueueVisitorMessage(
                $state->conversation_id,
                $body['content'],
                $this->latestVisitorTextMessageId($state->messages),
                $mediaMessageIds,
            );
        }

        // 管线入队会建立 debounce 租约，首次响应读取同一份聚合状态。
        $state->agent_activity = $this->activityRegistry->current($state->conversation_id);

        $response = response()->json($state);

        $cookie = $ctx->sessionCookie($request, $state->session_token);
        if ($cookie !== null) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * 读取并校验访客消息请求体。
     *
     * @return array{content: string, attachment_ids: list<string>, client_msg_id: ?string, quoted_message_id: ?string}
     */
    private function readMessageContent(Request $request): array
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string'],
            'attachment_ids' => ['nullable', 'array', 'max:'.AppendVisitorMessageAction::MAX_ATTACHMENT_COUNT],
            'attachment_ids.*' => ['string'],
            'client_msg_id' => ['nullable', 'string', 'max:64'],
            'quoted_message_id' => ['nullable', 'ulid'],
        ]);

        $content = trim((string) ($validated['content'] ?? ''));
        $attachmentIds = array_values(array_filter(
            array_map(static fn (string $id): string => trim($id), $validated['attachment_ids'] ?? []),
            static fn (string $id): bool => $id !== '',
        ));

        if ($content === '' && $attachmentIds === []) {
            throw ValidationException::withMessages(['content' => __('reception.errors.message_empty')]);
        }
        if (mb_strlen($content) > AppendVisitorMessageAction::MAX_CONTENT_LENGTH) {
            throw ValidationException::withMessages(['content' => __('reception.errors.message_too_long')]);
        }

        return [
            'content' => $content,
            'attachment_ids' => $attachmentIds,
            'client_msg_id' => $this->normalizeOptionalId($validated['client_msg_id'] ?? null),
            'quoted_message_id' => $this->normalizeOptionalId($validated['quoted_message_id'] ?? null),
        ];
    }

    /**
     * 返回最近一条访客文本消息 ID，供接待管线聚合与去重。
     *
     * @param  list<ReceptionMessageData>  $messages
     */
    private function latestVisitorTextMessageId(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i];
            if ($message->role === MessageRole::Visitor && $message->kind === MessageKind::Text) {
                return $message->id;
            }
        }

        return '';
    }

    /**
     * 返回本次图片和视频附件对应的消息 ID。
     *
     * @param  list<string>  $attachmentIds
     * @return list<string>
     */
    private function resolveMediaMessageIds(array $attachmentIds): array
    {
        if ($attachmentIds === []) {
            return [];
        }

        return Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('attachable_type', (new ConversationMessage)->getMorphClass())
            ->where(fn ($query) => $query
                ->where('mime_type', 'like', 'image/%')
                ->orWhere('mime_type', 'like', 'video/%'))
            ->pluck('attachable_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * 修剪可选 ID；空串归一为 null。
     */
    private function normalizeOptionalId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
