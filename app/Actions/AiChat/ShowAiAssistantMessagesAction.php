<?php

namespace App\Actions\AiChat;

use App\Data\AiChat\AiAssistantMessageData;
use App\Data\AiChat\AiAssistantThreadData;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantThread;
use App\Models\Attachment;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 读取当前联系人范围内的 AI 助手线程和持久化消息。
 */
class ShowAiAssistantMessagesAction
{
    use AsAction;

    private const int MAX_THREADS = 20;

    /**
     * 返回当前联系人最近的 AI 对话线程，并恢复图片附件地址。
     *
     * @return list<AiAssistantThreadData>
     */
    public function handle(Conversation $conversation): array
    {
        if (! Conversation::query()->whereKey($conversation->id)->exists()) {
            throw new NotFoundHttpException;
        }

        $threads = AiAssistantThread::query()
            ->forContactContext($conversation)
            ->with('messages')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_THREADS)
            ->get()
            ->reverse()
            ->values();

        if ($threads->isEmpty()) {
            return [];
        }

        $messages = $threads->flatMap(
            static fn (AiAssistantThread $thread): Collection => $thread->messages,
        );
        $attachmentUrls = $this->resolveAttachmentUrls($messages);

        return $threads
            ->map(function (AiAssistantThread $thread) use ($attachmentUrls): AiAssistantThreadData {
                $threadMessages = $thread->messages
                    ->map(function (AiAssistantMessage $message) use ($attachmentUrls): AiAssistantMessageData {
                        $imageUrls = array_values(array_filter(array_map(
                            static fn (string $attachmentId): ?string => $attachmentUrls->get($attachmentId),
                            $message->attachment_ids ?? [],
                        )));

                        return AiAssistantMessageData::fromModel($message, $imageUrls);
                    })
                    ->all();

                return new AiAssistantThreadData(
                    id: $thread->id,
                    messages: $threadMessages,
                );
            })
            ->all();
    }

    /**
     * 处理 AI 助手面板的会话消息查询请求。
     */
    public function asController(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
        ]);
        $conversation = Conversation::query()->find($validated['conversation_id']);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        $threads = $this->handle($conversation);

        return response()->json([
            'threads' => array_map(
                static fn (AiAssistantThreadData $thread): array => $thread->toArray(),
                $threads,
            ),
        ]);
    }

    /**
     * 批量解析消息引用的图片附件地址。
     *
     * @param  Collection<int, AiAssistantMessage>  $messages
     * @return Collection<string, string>
     */
    private function resolveAttachmentUrls(Collection $messages): Collection
    {
        $attachmentIds = $messages
            ->flatMap(static fn (AiAssistantMessage $message): array => $message->attachment_ids ?? [])
            ->unique()
            ->values()
            ->all();

        if ($attachmentIds === []) {
            return collect();
        }

        return Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->with('storageProfile')
            ->get()
            ->mapWithKeys(static fn (Attachment $attachment): array => [
                $attachment->id => $attachment->full_url,
            ]);
    }
}
