<?php

namespace App\Data\AiCallLog;

use App\Enums\AiCallPurpose;
use App\Models\AiCallLog;
use App\Models\Attachment;
use App\Models\ConversationMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * 应用后台 AI 调用日志详情数据，提供模型调用时间线、媒体和用量信息。
 */
class AiCallLogDetailData extends Data
{
    /**
     * 保存 AI 调用日志详情、用量和消息时间线。
     */
    public function __construct(
        /** 日志 ID */
        public string $id,
        public ?string $conversation_id,
        public AiCallPurpose $purpose,
        public string $purpose_label,
        public string $created_at,
        public string $last_at,
        public ?string $contact_id,
        /** 访客显示名；无联系人或非会话调用时为 null */
        public ?string $visitor_name,
        public string $model_name,
        public string $status,
        public int $turn_count,
        public int $total_input_tokens,
        public int $total_output_tokens,
        public ?int $duration_ms,
        /**
         * system prompt 快照列表，接待调用可含联系人历史会话背景。
         *
         * @var string[]
         */
        public array $system_prompts,
        /** @var AiCallLogToolDefinitionData[] */
        public array $available_tools,
        /** @var AiCallLogMessageData[] */
        public array $messages,
    ) {}

    /**
     * 从调用日志和关联会话消息组装详情。
     *
     * @param  Collection<int, ConversationMessage>  $conversationMessages
     */
    public static function fromLog(AiCallLog $log, Collection $conversationMessages, ?string $visitorName): self
    {
        $byId = $conversationMessages->keyBy(fn (ConversationMessage $m): string => (string) $m->id);

        $messages = array_map(
            fn (array $entry): AiCallLogMessageData => match ($entry['role']) {
                'user' => self::userMessage($entry, $byId),
                'assistant' => self::assistantMessage($entry),
            },
            $log->messages,
        );

        return new self(
            id: $log->id,
            conversation_id: $log->conversation_id,
            purpose: $log->purpose,
            purpose_label: $log->purpose->label(),
            created_at: $log->created_at->toIso8601String(),
            last_at: $log->last_at->toIso8601String(),
            contact_id: $log->contact_id,
            visitor_name: $visitorName,
            model_name: (string) $log->model_name,
            status: $log->status,
            turn_count: $log->turn_count,
            total_input_tokens: $log->input_tokens,
            total_output_tokens: $log->output_tokens,
            duration_ms: $log->duration_ms,
            system_prompts: $log->system_prompts,
            available_tools: array_map(
                static fn (array $tool): AiCallLogToolDefinitionData => new AiCallLogToolDefinitionData(
                    name: $tool['name'],
                    description: $tool['description'] !== '' ? $tool['description'] : null,
                    sent_to_visitor: $tool['name'] === 'respond',
                ),
                $log->available_tools,
            ),
            messages: $messages,
        );
    }

    /**
     * 组装一条用户消息及其图片附件。
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, ConversationMessage>  $byId
     */
    private static function userMessage(array $entry, Collection $byId): AiCallLogMessageData
    {
        $messageIds = array_values($entry['conversation_message_ids']);

        /** @var array<string, AiCallLogImageData> $imagesByUrl */
        $imagesByUrl = [];
        foreach ($messageIds as $id) {
            $message = $byId->get($id);
            if ($message === null) {
                continue;
            }
            foreach (self::imagesOf($message) as $image) {
                $imagesByUrl[$image->url] = $image;
            }
        }

        foreach (self::loggedImagesOf($entry) as $image) {
            $imagesByUrl[$image->url] ??= $image;
        }

        return new AiCallLogMessageData(
            role: 'user',
            turn_id: self::turnIdOf($entry),
            text: $entry['content'],
            segments: [],
            images: array_values($imagesByUrl),
            model_name: null,
            input_tokens: null,
            output_tokens: null,
            is_error: false,
            error_message: null,
            created_at: $entry['created_at'],
        );
    }

    /**
     * 组装一条模型消息及其分段和 token 统计。
     *
     * @param  array<string, mixed>  $entry
     */
    private static function assistantMessage(array $entry): AiCallLogMessageData
    {
        $turnId = self::turnIdOf($entry);

        return new AiCallLogMessageData(
            role: 'assistant',
            turn_id: $turnId,
            text: AiCallLog::assistantText($entry),
            segments: array_map(
                static fn (array $segment): AiCallLogSegmentData => AiCallLogSegmentData::fromStored($segment),
                $entry['segments'],
            ),
            images: [],
            model_name: $entry['model_name'] !== '' ? $entry['model_name'] : null,
            input_tokens: $entry['input_tokens'],
            output_tokens: $entry['output_tokens'],
            is_error: $entry['status'] === 'error',
            error_message: $entry['error_message'],
            created_at: $entry['created_at'],
        );
    }

    /**
     * 条目的接待轮次 ID；无则 null。
     *
     * @param  array<string, mixed>  $entry
     */
    private static function turnIdOf(array $entry): ?string
    {
        return $entry['turn_id'];
    }

    /**
     * 会话消息附带的图片（按附件公开 URL 展示）。
     *
     * @return AiCallLogImageData[]
     */
    private static function imagesOf(ConversationMessage $message): array
    {
        return $message->attachments
            ->filter(fn (Attachment $a): bool => Str::startsWith((string) $a->mime_type, 'image/'))
            ->map(fn (Attachment $a): AiCallLogImageData => new AiCallLogImageData(
                url: (string) $a->full_url,
                name: $a->original_name,
            ))
            ->values()
            ->all();
    }

    /**
     * 从调用日志媒体快照构建图片列表。
     *
     * @param  array<string, mixed>  $entry
     * @return AiCallLogImageData[]
     */
    private static function loggedImagesOf(array $entry): array
    {
        $images = [];

        foreach ($entry['media'] as $media) {
            if ($media['type'] !== 'image') {
                continue;
            }

            $images[] = new AiCallLogImageData(url: $media['url'], name: null);
        }

        return $images;
    }
}
