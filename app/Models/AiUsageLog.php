<?php

namespace App\Models;

use App\Enums\AiModelPurpose;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon $created_at 调用发生时间，用于按当日 / 本月聚合
 * @property string|null $ai_model_id 调用所用模型，指向 ai_models.id；模型删除或流式候选缺失时为空
 * @property string $model_name 调用时的模型名快照，便于模型行删除后仍可读
 * @property AiModelPurpose $purpose 调用用途：reception_chat / assistant / background_task 等
 * @property string|null $conversation_id 关联会话；后台助手等无会话场景为空
 * @property int $input_tokens 本次调用输入（prompt）token 数
 * @property int $output_tokens 本次调用输出（completion）token 数
 * @property int|null $models_count
 * @property-read AiModel|null $model
 */
class AiUsageLog extends Model
{
    /**
     * 单次 LLM 推理的 token 用量明细，由 UsageRecordingObserver 在 inference-stop 时写入。
     *
     * 仅用于按应用观测 / 归因（当日、本月 token 量），不计费、不拦截；追加写入、不更新，故无 updated_at。
     */
    use HasUlids;

    protected $table = 'ai_usage_logs';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'purpose' => AiModelPurpose::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    /**
     * 调用所用模型；模型已删除或流式候选缺失时为空。
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
