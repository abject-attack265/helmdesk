<?php

namespace App\Models;

use App\Enums\AiModelPurpose;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $ai_provider_id 所属 AI 供应商，指向 ai_providers.id
 * @property string $model_id 供应商侧的模型标识，调用时下发给上游 API，如 gpt-4o
 * @property string $name
 * @property string $type 模型能力类型：llm / embedding / rerank
 * @property AiModelPurpose $purpose 单一运行时用途；同 model 多用途拆成多行
 * @property bool $is_active 是否启用，停用后不参与运行时取用
 * @property int $weight 同用途内加权随机选择的权重，1-100，值越大被选中概率越高
 * @property bool $supports_image_input 是否支持图片内容块输入
 * @property bool $supports_video_input 是否支持视频内容块输入
 * @property int|null $providers_count
 * @property-read AiProvider $provider
 */
class AiModel extends Model
{
    /**
     * 当前应用的 AI 模型，一行对应「一个模型 + 一个用途」。
     *
     * purpose 决定用途池，weight 决定加权选择概率，媒体能力控制附件进入模型请求的形式。
     */
    use HasUlids;

    protected $table = 'ai_models';

    protected $guarded = [];

    /**
     * 定义模型用途与能力字段的类型转换。
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'purpose' => AiModelPurpose::class,
            'weight' => 'integer',
            'supports_image_input' => 'boolean',
            'supports_video_input' => 'boolean',
        ];
    }

    /**
     * 获取模型所属的 AI 供应商。
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }
}
