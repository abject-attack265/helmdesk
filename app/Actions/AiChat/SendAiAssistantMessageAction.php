<?php

namespace App\Actions\AiChat;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiModelPurpose;
use App\Jobs\AiChat\RunAiChatStreamJob;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AiRuntime\AiModelPool;
use App\Services\Realtime\MercureTopics;
use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 持久化收件箱会话中的 AI 助手提问，并派发后台流式任务生成回答。
 */
class SendAiAssistantMessageAction
{
    use AsAction;

    private const int MAX_HISTORY_MESSAGES = 20;

    private const int MAX_ATTACHMENT_COUNT = 6;

    /**
     * 注入模型池、系统设置、工具来源和问答轮次持久化动作。
     */
    public function __construct(
        private AiModelPool $aiModelPool,
        private CollectActiveIntegrationToolSourcesAction $collectIntegrationToolSources,
        private CollectActiveKnowledgeBasesAction $collectKnowledgeBases,
        private GeneralSettings $settings,
        private StartAiAssistantRoundAction $startRound,
    ) {}

    /**
     * 在指定客户会话中创建问答轮次并派发流式生成任务。
     *
     * @param  array<int, array{role: string, content: string, attachment_ids?: list<string>}>  $history
     * @param  list<string>  $attachmentIds  本轮提示词携带的图片/视频附件 ID
     * @return array{topic: string, thread_id: string, model: array{provider: string, name: string, model_id: string}}
     */
    public function handle(
        Conversation $conversation,
        User $user,
        string $prompt,
        string $roundId,
        array $history = [],
        array $attachmentIds = [],
        ?string $threadId = null,
    ): array {
        $trimmed = trim($prompt);
        if ($trimmed === '' && $attachmentIds === []) {
            throw ValidationException::withMessages([
                'prompt' => __('ai.chat.prompt_required'),
            ]);
        }

        if (! Str::isUuid($roundId)) {
            throw ValidationException::withMessages([
                'round_id' => __('validation.uuid', ['attribute' => 'round_id']),
            ]);
        }

        $models = $this->aiModelPool->modelsForPurpose(AiModelPurpose::Assistant);
        $model = $models->first();
        if ($model === null || $model->provider === null) {
            throw ValidationException::withMessages([
                'prompt' => __('ai.chat.selected_model_unavailable'),
            ]);
        }

        $messages = $this->buildMessagePayload($history, $trimmed, $attachmentIds);
        $round = $this->startRound->handle(
            $conversation,
            $user,
            $roundId,
            $trimmed,
            $attachmentIds,
            $threadId,
        );

        RunAiChatStreamJob::dispatch(
            $roundId,
            $models
                ->map(static fn (AiModel $candidate): RuntimeModelCandidateData => RuntimeModelCandidateData::fromModel($candidate))
                ->all(),
            $messages,
            $this->collectKnowledgeBases->handle(),
            $this->collectIntegrationToolSources->handle(),
            $this->settings->name,
            $user->resolvedTimezone(),
            $conversation->id,
            $round->thread_id,
            $round->assistant_message_id,
        );

        return [
            'topic' => MercureTopics::aiChat($roundId),
            'thread_id' => $round->thread_id,
            'model' => [
                'provider' => $model->provider->name,
                'name' => $model->name,
                'model_id' => $model->model_id,
            ],
        ];
    }

    /**
     * Laravel 路由入口：处理应用后台 AI 助手消息 POST。
     */
    public function asController(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'thread_id' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'round_id' => ['required', 'string', 'uuid'],
            'attachment_ids' => ['sometimes', 'array', 'max:'.self::MAX_ATTACHMENT_COUNT],
            'attachment_ids.*' => ['string'],
            'history' => ['sometimes', 'array', 'max:'.self::MAX_HISTORY_MESSAGES],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['nullable', 'string'],
            'history.*.attachment_ids' => ['sometimes', 'array', 'max:'.self::MAX_ATTACHMENT_COUNT],
            'history.*.attachment_ids.*' => ['string'],
        ]);
        $conversation = Conversation::query()->find($validated['conversation_id']);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        /** @var User $user */
        $user = $request->user();
        $history = array_values(array_map(
            static fn (array $entry): array => [
                'role' => $entry['role'],
                'content' => (string) ($entry['content'] ?? ''),
                'attachment_ids' => array_values($entry['attachment_ids'] ?? []),
            ],
            $validated['history'] ?? [],
        ));

        $payload = $this->handle(
            $conversation,
            $user,
            (string) ($validated['prompt'] ?? ''),
            (string) $validated['round_id'],
            $history,
            array_values($validated['attachment_ids'] ?? []),
            isset($validated['thread_id']) ? (string) $validated['thread_id'] : null,
        );

        return response()->json($payload, Response::HTTP_ACCEPTED);
    }

    /**
     * 规范化历史消息并追加本轮客服输入。
     *
     * @param  array<int, array{role: string, content: string, attachment_ids?: list<string>}>  $history
     * @param  list<string>  $attachmentIds
     * @return array<int, array{role: string, content: string, attachment_ids: list<string>}>
     */
    private function buildMessagePayload(array $history, string $prompt, array $attachmentIds): array
    {
        $messages = [];

        foreach (array_slice($history, -self::MAX_HISTORY_MESSAGES) as $entry) {
            $role = $entry['role'];
            $content = trim($entry['content']);
            $entryAttachmentIds = $entry['attachment_ids'] ?? [];

            if ($content === '' && $entryAttachmentIds === []) {
                throw ValidationException::withMessages([
                    'history' => __('validation.required', ['attribute' => 'history']),
                ]);
            }

            if (! in_array($role, ['user', 'assistant'], true)) {
                throw ValidationException::withMessages([
                    'history' => __('validation.in', ['attribute' => 'history.role']),
                ]);
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
                'attachment_ids' => $entryAttachmentIds,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
            'attachment_ids' => $attachmentIds,
        ];

        return $messages;
    }
}
