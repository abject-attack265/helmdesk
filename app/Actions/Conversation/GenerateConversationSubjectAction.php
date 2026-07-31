<?php

namespace App\Actions\Conversation;

use App\Enums\AiCallPurpose;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\NeuronChatGenerator;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Conversation\ConversationLlmCandidateResolver;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * 为会话生成并保存自动主题。
 */
class GenerateConversationSubjectAction
{
    use AsAction;

    private const int MAX_MESSAGES = 3;

    private const int MAX_MESSAGE_LENGTH = 500;

    private const int MAX_SUBJECT_LENGTH = 60;

    private const string SUBJECT_WRAPPER_PATTERN = '/^[\s"\'`“”‘’。\.]+|[\s"\'`“”‘’。\.]+$/u';

    /**
     * 注入聊天生成原语、后台任务候选模型解析器和实时通知服务。
     */
    public function __construct(
        private readonly NeuronChatGenerator $generator,
        private readonly ConversationLlmCandidateResolver $candidates,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 主题生成指令：把访客前几条消息概括成一个 4-12 字的简短标题。
     */
    private function subjectInstructions(): string
    {
        return '你是客服会话标题助手。根据访客的前几条消息，用一个简短的标题（不超过 12 个字）概括本次会话的核心诉求。'
            .'只输出标题本身，不要引号、标点结尾或多余说明，使用与访客相同的语言。';
    }

    /**
     * 根据会话前几条访客文本消息为空主题写入短标题。
     */
    public function handle(Conversation $conversation): ?string
    {
        if (filled($conversation->subject)) {
            return $conversation->subject;
        }

        $messages = $this->collectVisitorMessages($conversation);
        if ($messages === []) {
            throw new RuntimeException('Conversation subject generation requires visitor text messages.');
        }

        $subject = $this->generateWithCandidates($conversation, $messages);
        if ($subject === null) {
            throw new RuntimeException('Conversation subject generation returned an empty subject.');
        }

        $affected = Conversation::query()
            ->whereKey($conversation->id)
            ->whereNull('subject')
            ->update([
                'subject' => $subject,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            $currentSubject = Conversation::query()->whereKey($conversation->id)->value('subject');

            return is_string($currentSubject) ? $currentSubject : null;
        }

        $updated = $conversation->fresh();
        if ($updated !== null) {
            $this->realtimeNotifier->conversationChanged($updated, 'conversation_subject_updated', [
                'subject' => $subject,
            ]);
        }

        return $subject;
    }

    /**
     * 读取会话开头的访客文本消息并裁剪输入长度。
     *
     * @return list<string>
     */
    private function collectVisitorMessages(Conversation $conversation): array
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::Visitor)
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->orderBy('seq_no')
            ->limit(self::MAX_MESSAGES)
            ->pluck('content')
            ->map(fn (mixed $content): string => trim(Str::limit((string) $content, self::MAX_MESSAGE_LENGTH, '')))
            ->filter(fn (string $content): bool => $content !== '')
            ->values()
            ->all();
    }

    /**
     * 按后台任务用途候选模型顺序生成标题（首个成功即返回）。
     *
     * @param  list<string>  $messages
     */
    private function generateWithCandidates(Conversation $conversation, array $messages): ?string
    {
        $lastError = null;

        foreach ($this->candidates->resolve($conversation) as $model) {
            try {
                return $this->normalizeSubject(
                    $this->generator->generate(
                        $model,
                        $this->subjectInstructions(),
                        implode("\n", $messages),
                        AiUsageContext::forModel($model, (string) $conversation->id, AiCallPurpose::ConversationSubject),
                    ),
                );
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('会话主题生成候选模型失败', [
                    'conversation_id' => $conversation->id,
                    'ai_model_id' => $model->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        throw new RuntimeException('Conversation subject generation requires a usable background task model.');
    }

    /**
     * 清理模型输出中的包裹符号并裁剪标题长度。
     */
    private function normalizeSubject(?string $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        if (! mb_check_encoding($subject, 'UTF-8')) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($subject));
        if (! is_string($normalized)) {
            return null;
        }

        $normalized = preg_replace(self::SUBJECT_WRAPPER_PATTERN, '', $normalized);
        if (! is_string($normalized)) {
            return null;
        }

        $normalized = Str::limit($normalized, self::MAX_SUBJECT_LENGTH, '');

        return $normalized !== '' ? $normalized : null;
    }
}
