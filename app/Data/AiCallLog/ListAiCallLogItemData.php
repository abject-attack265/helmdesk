<?php

namespace App\Data\AiCallLog;

use App\Enums\AiCallPurpose;
use App\Models\AiCallLog;
use Spatie\LaravelData\Data;

/**
 * 应用设置「AI 调用日志」列表项：一行=一个 AI 会话。
 * 由 ShowAiCallLogListAction 组装，传给 resources/js/pages/appSettings/aiCallLog/List.vue；
 * 详情按 id 拉取整段对话（AiCallLogDetailData）。
 */
class ListAiCallLogItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $conversation_id,
        public AiCallPurpose $purpose,
        public string $purpose_label,
        public string $model_name,
        public ?string $output_preview,
        public string $status,
        public int $turn_count,
        public int $input_tokens,
        public int $output_tokens,
        public string $created_at,
        public string $last_at,
        public ?string $contact_id,
    ) {}

    /**
     * 从日志行构造（列表查询不加载 messages 等大 JSON 列）。
     */
    public static function fromModel(AiCallLog $log): self
    {
        return new self(
            id: $log->id,
            conversation_id: $log->conversation_id,
            purpose: $log->purpose,
            purpose_label: $log->purpose->label(),
            model_name: (string) $log->model_name,
            output_preview: $log->reply_preview !== '' ? $log->reply_preview : null,
            status: $log->status,
            turn_count: $log->turn_count,
            input_tokens: $log->input_tokens,
            output_tokens: $log->output_tokens,
            created_at: $log->created_at->toIso8601String(),
            last_at: $log->last_at->toIso8601String(),
            contact_id: $log->contact_id,
        );
    }
}
