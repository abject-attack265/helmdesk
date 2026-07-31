<?php

namespace App\Services\Ai\Logging;

use App\Enums\AiCallPurpose;
use App\Models\AiCallLog;
use App\Services\Ai\Usage\AiUsageContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\ObserverInterface;
use NeuronAI\Tools\ToolInterface;
use Throwable;

/**
 * 将 NeuronAI agent 运行记录成可审计的用户输入与模型输出时间线。
 *
 * 接待调用按会话合并多轮记录，其余调用每次运行独立成行。观测失败记录 warning，
 * 不影响模型调用结果。
 */
final class CallLoggingObserver implements ObserverInterface
{
    /** 标识本次 agent 运行。 */
    private readonly string $runId;

    /**
     * 本次运行的 user 条目（首个 inference-start 时从新消息抽取）。
     *
     * @var array<string, mixed>|null
     */
    private ?array $userEntry = null;

    /**
     * 本次运行累积中的 assistant 条目。
     *
     * @var array<string, mixed>|null
     */
    private ?array $assistantEntry = null;

    /** 本次运行写入的调用日志。 */
    private ?AiCallLog $row = null;

    /**
     * 当前等待响应的请求计时（inference-start 时填，stop / error 时消费）。
     *
     * @var array{started_micro: float}|null
     */
    private ?array $pending = null;

    /** 标记当前响应是否等待工具执行结果。 */
    private bool $expectingToolResults = false;

    public function __construct(
        private readonly AiUsageContext $context,
        private readonly Agent $agent,
    ) {
        $this->runId = (string) Str::uuid();
    }

    /**
     * 处理 NeuronAI 事件：inference-start 抓输入，inference-stop 累积成功分段，error 记失败。
     */
    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        try {
            match ($event) {
                'inference-start' => $this->onInferenceStart($data),
                'inference-stop' => $this->onInferenceStop($data),
                'tool-called' => $this->onToolCalled($data),
                'error' => $this->onError($data),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::warning('[ai-call-log] 调用日志写入失败', [
                'call_purpose' => $this->context->callPurpose?->value,
                'model_name' => $this->context->modelName,
                'run_id' => $this->runId,
                'event' => $event,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
            $this->pending = null;
        }
    }

    /**
     * 开始一轮来回：记计时；首轮从新消息抽取本次运行的 user 条目。
     */
    private function onInferenceStart(mixed $data): void
    {
        if (! $data instanceof InferenceStart) {
            return;
        }

        $this->pending = [
            'started_micro' => microtime(true),
        ];

        if ($this->userEntry === null && $data->message->getRole() === MessageRole::USER->value) {
            $this->userEntry = [
                'role' => 'user',
                'run_id' => $this->runId,
                'turn_id' => $this->context->turnId,
                'created_at' => now()->toIso8601String(),
                'content' => $this->textOf($data->message),
                'media' => $this->mediaOf($data->message),
                'conversation_message_ids' => array_values($this->context->conversationMessageIds),
            ];
        }
    }

    /**
     * 成功结束一轮来回：把响应文本追加为 text 分段，累计 token 与耗时并整行落库。
     */
    private function onInferenceStop(mixed $data): void
    {
        if (! $data instanceof InferenceStop || $this->pending === null) {
            return;
        }

        $usage = $data->response->getUsage();
        $entry = &$this->assistantEntryRef();
        $entry['input_tokens'] += $usage instanceof Usage ? max(0, $usage->inputTokens) : 0;
        $entry['output_tokens'] += $usage instanceof Usage ? max(0, $usage->outputTokens) : 0;
        $entry['duration_ms'] += $this->elapsedMs();
        $this->pending = null;

        $text = $this->textOf($data->response);
        if ($text !== '') {
            $entry['segments'][] = ['type' => 'text', 'content' => $text];
        }

        // 响应是工具调用时，紧随其后的 tool-called 事件会把各工具执行结果追加为 tool_call 分段。
        $this->expectingToolResults = $data->response instanceof ToolCallMessage;
        unset($entry);

        $this->persist();
    }

    /**
     * 工具执行完成：把名称 / 入参 / 返回结果追加为 tool_call 分段（含 respond 的发送结果）。
     */
    private function onToolCalled(mixed $data): void
    {
        if (! $data instanceof ToolCalled || ! $this->expectingToolResults) {
            return;
        }

        $entry = &$this->assistantEntryRef();
        $entry['segments'][] = [
            'type' => 'tool_call',
            'name' => $data->tool->getName(),
            'inputs' => $data->tool->getInputs(),
            'result' => $this->toolResultOf($data->tool),
        ];
        unset($entry);

        $this->persist();
    }

    /**
     * 推理出错：把本次运行的 assistant 条目标记为失败并落库。
     */
    private function onError(mixed $data): void
    {
        if (! $data instanceof AgentError || $this->pending === null) {
            return;
        }

        $entry = &$this->assistantEntryRef();
        $entry['duration_ms'] += $this->elapsedMs();
        $entry['status'] = 'error';
        $entry['error_message'] = $data->exception->getMessage();
        unset($entry);
        $this->pending = null;
        $this->expectingToolResults = false;

        $this->persist();
    }

    /**
     * 将业务结果不可用的最近一次模型运行标记为失败。
     */
    public function markLastRowFailed(string $message): void
    {
        try {
            if ($this->assistantEntry === null) {
                return;
            }

            $this->assistantEntry['status'] = 'error';
            $this->assistantEntry['error_message'] = $message;
            $this->persist();
        } catch (Throwable $exception) {
            Log::warning('[ai-call-log] 调用日志标记失败状态时写入失败', [
                'call_purpose' => $this->context->callPurpose?->value,
                'model_name' => $this->context->modelName,
                'run_id' => $this->runId,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 取（或初始化）本次运行累积中的 assistant 条目引用。
     *
     * @return array<string, mixed>
     */
    private function &assistantEntryRef(): array
    {
        if ($this->assistantEntry === null) {
            $this->assistantEntry = [
                'role' => 'assistant',
                'run_id' => $this->runId,
                'turn_id' => $this->context->turnId,
                'created_at' => now()->toIso8601String(),
                'segments' => [],
                'model_name' => $this->context->modelName,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'duration_ms' => 0,
                'status' => 'success',
                'error_message' => null,
            ];
        }

        return $this->assistantEntry;
    }

    /**
     * 合并并保存本次运行的用户输入和模型输出。
     */
    private function persist(): void
    {
        $row = $this->resolveRow();

        $kept = array_values(array_filter(
            $row->messages ?? [],
            fn (array $entry): bool => ($entry['run_id'] ?? null) !== $this->runId,
        ));

        $fresh = [];
        if ($this->userEntry !== null && ! $this->hasSameUserEntry($kept)) {
            $fresh[] = $this->userEntry;
        }
        if ($this->assistantEntry !== null) {
            $fresh[] = $this->assistantEntry;
        }

        $row->messages = [...$kept, ...$fresh];
        $row->model_name = $this->context->modelName;
        $row->system_prompts = $this->systemPrompts();
        $row->available_tools = array_map(
            static fn (ToolInterface $tool): array => [
                'name' => $tool->getName(),
                'description' => (string) $tool->getDescription(),
            ],
            $this->agent->getTools(),
        );
        $row->save();
    }

    /**
     * 解析本次运行所属的调用日志。
     */
    private function resolveRow(): AiCallLog
    {
        if ($this->row !== null) {
            return $this->row;
        }

        $isConversationScoped = $this->context->callPurpose === AiCallPurpose::ReceptionReply
            && $this->context->conversationId !== null;

        $this->row = $isConversationScoped
            ? AiCallLog::query()->firstOrNew([
                'purpose' => AiCallPurpose::ReceptionReply,
                'conversation_id' => $this->context->conversationId,
            ])
            : new AiCallLog(['conversation_id' => $this->context->conversationId]);

        if (! $this->row->exists) {
            $this->row->fill([
                'created_at' => now(),
                'purpose' => $this->context->callPurpose,
                'conversation_message_id' => $this->context->conversationMessageId,
                'contact_id' => $this->context->contactId,
                'messages' => [],
            ]);
        }

        return $this->row;
    }

    /**
     * 判断接待轮次的用户输入是否已经记录。
     *
     * @param  list<array<string, mixed>>  $entries
     */
    private function hasSameUserEntry(array $entries): bool
    {
        if ($this->context->turnId === null) {
            return false;
        }

        foreach ($entries as $entry) {
            if (($entry['role'] ?? null) === 'user'
                && ($entry['turn_id'] ?? null) === $this->context->turnId
                && ($entry['content'] ?? null) === ($this->userEntry['content'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 收集 agent 指令与聊天历史中的 SYSTEM 消息快照。
     *
     * @return list<string>
     */
    private function systemPrompts(): array
    {
        $prompts = [];

        $instructions = trim((string) $this->agent->resolveInstructions());
        if ($instructions !== '') {
            $prompts[] = $instructions;
        }

        foreach ($this->agent->getChatHistory()->getMessages() as $message) {
            if ($message->getRole() === MessageRole::SYSTEM->value) {
                $text = $this->textOf($message);
                if ($text !== '') {
                    $prompts[] = $text;
                }
            }
        }

        return $prompts;
    }

    /**
     * 拼接消息中的全部文本块。
     */
    private function textOf(Message $message): string
    {
        $parts = [];
        foreach ($message->getContentBlocks() as $block) {
            if ($block instanceof TextContent && $block->content !== '') {
                $parts[] = $block->content;
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * 提取消息媒体类型和图片公开地址。
     *
     * @return list<array{type: string, url?: string}>
     */
    private function mediaOf(Message $message): array
    {
        $media = [];
        foreach ($message->getContentBlocks() as $block) {
            if (! $block instanceof ImageContent
                && ! $block instanceof VideoContent
                && ! $block instanceof AudioContent
                && ! $block instanceof FileContent) {
                continue;
            }

            $item = ['type' => $block->getType()->value];
            if ($block instanceof ImageContent) {
                if ($block->sourceType !== SourceType::URL) {
                    throw new \LogicException('AI 调用日志要求图片使用 URL 内容源。');
                }

                $item['url'] = $block->content;
            }

            $media[] = $item;
        }

        return $media;
    }

    /**
     * 工具执行结果转字符串（数组结果 JSON 编码，与前端展示约定一致）。
     */
    private function toolResultOf(ToolInterface $tool): string
    {
        $result = $tool->getResult();

        return is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 本轮来回耗时（毫秒），从 inference-start 到当前。
     */
    private function elapsedMs(): int
    {
        return (int) round((microtime(true) - $this->pending['started_micro']) * 1000);
    }
}
