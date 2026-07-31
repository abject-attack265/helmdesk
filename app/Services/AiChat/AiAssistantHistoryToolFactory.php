<?php

namespace App\Services\AiChat;

use App\Actions\AiChat\ReadAiAssistantHistoryAction;
use App\Actions\AiChat\SearchAiAssistantHistoryAction;
use App\Models\Conversation;
use DateTimeZone;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 为 AI 助手装配当前联系人历史 AI 对话的定位与读取工具。
 */
class AiAssistantHistoryToolFactory
{
    /**
     * 注入历史 AI 对话检索动作。
     */
    public function __construct(
        private readonly SearchAiAssistantHistoryAction $searchHistory,
        private readonly ReadAiAssistantHistoryAction $readHistory,
    ) {}

    /**
     * 构造只读的 ai_assistant_history 工具。
     */
    public function build(
        string $conversationId,
        string $currentThreadId,
        DateTimeZone $timezone,
    ): Tool {
        return Tool::make(
            'ai_assistant_history',
            '检索当前联系人的历史 AI 对话。mode=search 时用多个关键词定位消息；mode=read 时按 thread_id、offset、limit 读取连续消息。可先 search，再用命中结果建议的位置 read 获取完整上下文。',
        )
            ->addProperty(new ToolProperty(
                'mode',
                PropertyType::STRING,
                '操作：search（关键词定位）或 read（读取连续消息）。',
                true,
                ['search', 'read'],
            ))
            ->addProperty(new ToolProperty(
                'thread_id',
                PropertyType::STRING,
                'read 时必填，历史线程 ID。',
            ))
            ->addProperty(new ToolProperty(
                'offset',
                PropertyType::INTEGER,
                '从 0 开始的位置。search 表示跳过的命中数；read 表示跳过的消息数。',
            ))
            ->addProperty(new ToolProperty(
                'limit',
                PropertyType::INTEGER,
                '返回条数。search 最大 10，read 最大 30。',
            ))
            ->addProperty(new ArrayProperty(
                'keywords',
                'search 时必填，要在历史 AI 问答正文中查找的 1–5 个简短关键词或原句片段，任一命中即可。',
                false,
                new ToolProperty('keyword', PropertyType::STRING, '单个关键词或原句片段。', true),
            ))
            ->setCallable(function (
                ?string $mode,
                ?string $thread_id,
                ?int $offset,
                ?int $limit,
                ?array $keywords,
            ) use ($conversationId, $currentThreadId, $timezone): array {
                $conversation = Conversation::query()->findOrFail($conversationId);

                if ($mode === 'search') {
                    if ($keywords === null || $keywords === []) {
                        return ['error' => 'keywords_required'];
                    }

                    return $this->searchHistory->handle(
                        $conversation,
                        $currentThreadId,
                        $keywords,
                        $timezone,
                        $offset ?? 0,
                        $limit ?? 5,
                    )->toArray();
                }

                if ($mode !== 'read' || trim((string) $thread_id) === '') {
                    return ['error' => 'mode_or_thread_id_invalid'];
                }

                try {
                    return $this->readHistory->handle(
                        $conversation,
                        $currentThreadId,
                        (string) $thread_id,
                        $timezone,
                        $offset ?? 0,
                        $limit ?? 12,
                    )->toArray();
                } catch (NotFoundHttpException) {
                    return ['error' => 'ai_assistant_history_thread_inaccessible'];
                }
            });
    }
}
