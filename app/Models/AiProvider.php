<?php

namespace App\Models;

use App\Enums\AiProviderProtocol;
use App\Models\Concerns\HasCredentialFields;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $brand 品牌目录标识，如 openai / deepseek / qwen / azure / ollama，决定预设 base_url 和图标
 * @property string $slug
 * @property string $name
 * @property AiProviderProtocol $protocol 底层调用协议：openai / anthropic / gemini，各品牌最终映射到这三种原生通道之一
 * @property string|null $icon 图标标识或 URL，缺省时按 brand 取默认图标
 * @property string|null $credentials 加密存储的凭据 JSON（api_key、base_url 等）
 * @property array $credential_fields 凭据表单字段定义：field/label/secret 等，用于动态渲染设置页
 * @property int|null $models_count
 * @property-read Collection|AiModel[] $models
 */
class AiProvider extends Model
{
    /**
     * 当前应用的 AI 供应商模型，保存协议、凭据字段、图标和连接配置。
     *
     * 运行时由当前应用的模型用途池按需取用其下模型。
     */
    use HasCredentialFields, HasUlids;

    protected $table = 'ai_providers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'protocol' => AiProviderProtocol::class,
            'credentials' => 'encrypted:array',
            'credential_fields' => 'array',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }
}
