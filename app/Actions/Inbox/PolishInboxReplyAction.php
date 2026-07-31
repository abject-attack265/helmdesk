<?php

namespace App\Actions\Inbox;

use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormPolishInboxReplyData;
use App\Data\Inbox\InboxReplyPolishCandidateData;
use App\Data\Inbox\InboxReplyPolishContextData;
use App\Data\Inbox\InboxReplyPolishResultData;
use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\ReplyAssistantMode;
use App\Enums\ReplyPolishTone;
use App\Exceptions\BusinessException;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Ai\MultimodalMessageBuilder;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\InboxReplyPolishSchema;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\AiModelPool;
use App\Services\Conversation\ConversationReplyRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * 使用 AI 为收件箱客服生成或改写候选回复。
 * 润色属后台任务：按 background_task 用途从全局池取候选模型，逐个尝试、失败 fallback。
 */
class PolishInboxReplyAction
{
    use AsAction;

    /** 喂给模型的最近访客图片/视频消息上限，控制 token 成本。 */
    private const int MAX_VISITOR_MEDIA = 6;

    /**
     * 注入回复校验、后台任务用途模型池、上下文构建、结构化生成原语和附件消息构建器。
     */
    public function __construct(
        private readonly ConversationReplyRule $replyRule,
        private readonly AiModelPool $aiModelPool,
        private readonly BuildInboxReplyPolishContextAction $buildContext,
        private readonly NeuronStructuredGenerator $generator,
        private readonly MultimodalMessageBuilder $messageBuilder,
    ) {}

    /**
     * 校验会话后，按候选模型顺序调用 AI 运行时返回候选回复。
     */
    public function handle(User $user, string $conversationId, FormPolishInboxReplyData $data): InboxReplyPolishResultData
    {
        $conversation = Conversation::query()

            ->find($conversationId);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        $denialMessageKey = $this->replyRule->denialMessageKey($conversation, $user);
        if ($denialMessageKey !== null) {
            throw new BusinessException(__($denialMessageKey));
        }

        $context = $this->buildContext->handle($conversation, $data->quoted_message_id, $user->locale);
        $candidateContents = $this->generateWithCandidates($conversation, $data, $context);

        $candidates = [];
        foreach ($candidateContents as $index => $content) {
            $candidates[] = InboxReplyPolishCandidateData::fromContent($index, $content);
        }

        return new InboxReplyPolishResultData($candidates);
    }

    /**
     * 按后台任务用途候选模型顺序生成候选回复（首个成功即返回）。
     *
     * @return list<string>
     */
    private function generateWithCandidates(
        Conversation $conversation,
        FormPolishInboxReplyData $data,
        InboxReplyPolishContextData $context,
    ): array {
        $lastError = null;
        foreach ($this->aiModelPool->modelsForPurpose(AiModelPurpose::BackgroundTask) as $model) {
            try {
                $schema = $this->generator->generateFromMessage(
                    $model,
                    $this->polishInstructions($data->mode, $data->tone, $context),
                    $this->buildPolishMessage($conversation, $data, $context, $model),
                    InboxReplyPolishSchema::class,
                    AiUsageContext::forModel($model, (string) $conversation->id, AiCallPurpose::ReplyPolish),
                );
                $candidateContents = array_values(array_filter(
                    array_map(static fn (mixed $c): string => is_string($c) ? trim($c) : '', $schema->candidates),
                    static fn (string $c): bool => $c !== '',
                ));
                if ($candidateContents === []) {
                    throw new RuntimeException('Inbox reply assistant returned empty candidates.');
                }

                return $candidateContents;
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('收件箱 AI 回复助手失败', [
                    'conversation_id' => $conversation->id,
                    'ai_model_id' => $model->id,
                    'error' => $this->sanitizeUpstreamError($exception->getMessage()),
                ]);
            }
        }

        throw new BusinessException(__('conversation.errors.reply_polish_failed'));
    }

    /**
     * 回复助手指令：约束模式（撰写/改写）、语气与语言对齐。
     */
    private function polishInstructions(ReplyAssistantMode $mode, ReplyPolishTone $tone, InboxReplyPolishContextData $context): string
    {
        $modeText = match ($mode) {
            ReplyAssistantMode::Reply => '根据会话上下文，替客服撰写一条得体、可直接发送的回复。',
            ReplyAssistantMode::Rewrite => '在保持原意不变的前提下，改写客服给出的回复草稿。',
        };

        $toneText = match ($tone) {
            ReplyPolishTone::Keep => '保持草稿原有语气。',
            ReplyPolishTone::Professional => '使用专业、正式的语气。',
            ReplyPolishTone::Friendly => '使用亲切、友好的语气。',
            ReplyPolishTone::Concise => '使用简洁、直接的语气。',
        };

        $replyLocale = filled($context->teammate_locale) ? $context->teammate_locale : $context->visitor_locale;
        $localeText = filled($replyLocale) ? '回复请使用 '.$replyLocale.' 语言。' : '回复请使用与访客一致的语言。';

        return '你是资深客服回复助手。'.$modeText.$toneText.$localeText
            .'生成 1 到 3 条候选回复，每条都完整、可直接发送，不要包含解释性说明。';
    }

    /**
     * 按模型能力把文本上下文与最近的访客媒体拼成用户消息。
     */
    private function buildPolishMessage(
        Conversation $conversation,
        FormPolishInboxReplyData $data,
        InboxReplyPolishContextData $context,
        AiModel $model,
    ): UserMessage {
        $content = trim((string) $data->content);
        $text = "# 会话上下文\n\n".json_encode($context->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($data->mode === ReplyAssistantMode::Rewrite || $content !== '') {
            $text .= "\n\n# 客服回复草稿\n\n".$content;
        }

        $blocks = [new TextContent($text)];

        foreach ($this->collectVisitorMediaBlocks($conversation, $model) as $block) {
            $blocks[] = $block;
        }

        return new UserMessage($blocks);
    }

    /**
     * 取最近若干条访客图片/视频消息，构建成内容块（按时间升序）。
     *
     * @return list<ContentBlockInterface>
     */
    private function collectVisitorMediaBlocks(Conversation $conversation, AiModel $model): array
    {
        $mediaMessages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::Visitor)
            ->whereIn('kind', [MessageKind::Image, MessageKind::File])
            ->whereNull('recalled_at')
            ->with('attachments.storageProfile')
            ->orderByDesc('seq_no')
            ->limit(self::MAX_VISITOR_MEDIA)
            ->get()
            ->sortBy('seq_no')
            ->values();

        $blocks = [];
        foreach ($mediaMessages as $message) {
            foreach ($this->messageBuilder->attachmentBlocks(
                $message->attachments,
                $model->supports_image_input,
                $model->supports_video_input,
                (string) $model->id,
            ) as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * 接收收件箱 AI 回复助手请求并返回 JSON。
     */
    public function asController(Request $request, string $conversationId): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);
        $data = FormPolishInboxReplyData::from($request);

        return response()->json($this->handle(
            user: $user,
            conversationId: $conversationId,
            data: $data,
        )->toArray());
    }

    /**
     * 脱敏并裁短上游错误，避免凭据进入日志。
     */
    private function sanitizeUpstreamError(string $message): string
    {
        $patterns = [
            '/sk-[A-Za-z0-9_\-]{16,}/i' => '[redacted-key]',
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [redacted]',
            '/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/' => '[redacted-jwt]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = (string) preg_replace($pattern, $replacement, $message);
        }

        return mb_substr($message, 0, 200);
    }
}
